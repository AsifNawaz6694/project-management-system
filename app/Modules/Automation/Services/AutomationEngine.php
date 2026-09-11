<?php

namespace App\Modules\Automation\Services;

use App\Modules\Automation\Models\AutomationAction as Action;
use App\Modules\Automation\Models\AutomationCondition as Condition;
use App\Modules\Automation\Models\AutomationRule;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Services\NotificationService;
use App\Modules\TaskManagement\Models\Label;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use App\Modules\TaskManagement\Services\TaskService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the "when / if / then" rules.
 *
 * Rules fire from the task domain events, synchronously, inside the same
 * request that caused them. Two properties matter more than anything else:
 *
 *   - **A broken rule must never break a task.** Every rule and every action is
 *     wrapped, and a failure becomes an `automation_runs` row, not an exception
 *     reaching the user.
 *   - **Rules must not loop.** An action that changes a task re-dispatches the
 *     task events, which re-enter this engine. A depth limit plus a per-chain
 *     record of which rules already ran on which task stops the cycle.
 */
class AutomationEngine
{
    /** How many times a chain of rules may re-trigger itself. */
    public const MAX_DEPTH = 5;

    private static int $depth = 0;

    /** @var array<string, true> "ruleId:taskId" pairs already run in this chain. */
    private static array $executed = [];

    private static bool $enabled = true;

    public function __construct(
        private readonly TaskService $tasks,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Turn the engine off for the duration of a callback — used by seeders and
     * by tests that are not exercising automation.
     */
    public static function withoutRules(callable $callback): mixed
    {
        $previous = static::$enabled;
        static::$enabled = false;

        try {
            return $callback();
        } finally {
            static::$enabled = $previous;
        }
    }

    public static function reset(): void
    {
        static::$depth = 0;
        static::$executed = [];
        static::$enabled = true;
    }

    /**
     * @param  array<string, mixed>  $context  extra facts from the event payload
     */
    public function run(string $trigger, Task $task, array $context = []): void
    {
        if (! static::$enabled || static::$depth >= self::MAX_DEPTH) {
            return;
        }

        $rules = $this->rulesFor($trigger, $task);

        if ($rules->isEmpty()) {
            return;
        }

        static::$depth++;

        try {
            foreach ($rules as $rule) {
                $fingerprint = $rule->id.':'.$task->id;

                if (isset(static::$executed[$fingerprint])) {
                    continue; // Already ran on this task in this chain.
                }

                static::$executed[$fingerprint] = true;

                $this->attempt($rule, $task, $context);
            }
        } finally {
            static::$depth--;

            if (static::$depth === 0) {
                static::$executed = [];
            }
        }
    }

    /**
     * @return Collection<int, AutomationRule>
     */
    private function rulesFor(string $trigger, Task $task): Collection
    {
        return AutomationRule::query()
            ->with(['conditions', 'actions'])
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('project_id')->orWhere('project_id', $task->project_id))
            ->orderBy('run_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function attempt(AutomationRule $rule, Task $task, array $context): void
    {
        try {
            if (! $this->matches($rule, $task, $context)) {
                return;
            }

            foreach ($rule->actions as $action) {
                $this->execute($action, $task, $context, $rule);
            }

            $this->record($rule, $task, AutomationRun::STATUS_SUCCESS, null);

            $rule->forceFill([
                'last_run_at' => now(),
                'run_count' => $rule->run_count + 1,
            ])->saveQuietly();
        } catch (Throwable $e) {
            // A rule that throws is a configuration problem, not a reason to
            // fail the user's save.
            Log::warning('Automation rule failed', ['rule' => $rule->id, 'task' => $task->id, 'error' => $e->getMessage()]);
            $this->record($rule, $task, AutomationRun::STATUS_FAILED, $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Conditions
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $context
     */
    public function matches(AutomationRule $rule, Task $task, array $context = []): bool
    {
        foreach ($rule->conditions as $condition) {
            if (! $this->conditionHolds($condition, $task, $context)) {
                return false;
            }
        }

        return true; // No conditions means "always".
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function conditionHolds(Condition $condition, Task $task, array $context): bool
    {
        $actual = $this->readField($condition->field, $task, $context);
        $expected = $condition->value;

        return match ($condition->operator) {
            'equals' => $this->scalar($actual) === $this->scalar($expected),
            'not_equals' => $this->scalar($actual) !== $this->scalar($expected),
            'in' => in_array($this->scalar($actual), $this->list($expected), true),
            'not_in' => ! in_array($this->scalar($actual), $this->list($expected), true),
            'contains' => $this->contains($actual, (string) $expected),
            'not_contains' => ! $this->contains($actual, (string) $expected),
            'is_empty' => $this->isEmpty($actual),
            'is_not_empty' => ! $this->isEmpty($actual),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function readField(string $field, Task $task, array $context): mixed
    {
        return match ($field) {
            Condition::FIELD_FROM_STATUS => $context['from_status'] ?? null,
            Condition::FIELD_TO_STATUS => $context['to_status'] ?? $task->status,
            Condition::FIELD_CHANGED_FIELD => $context['changed_fields'] ?? [],
            Condition::FIELD_COMMENT => $context['comment_body'] ?? null,
            Condition::FIELD_ACTOR => $context['actor_id'] ?? null,
            Condition::FIELD_LABEL => $task->labels->pluck('name')->all(),
            Condition::FIELD_OVERDUE => $task->due_date && $task->due_date->isPast() && ! $task->completed_at ? '1' : '0',
            default => $task->getAttribute($field),
        };
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_array($value) ? null : (string) $value;
    }

    /**
     * A condition value is stored as text; a list is stored as JSON or as a
     * comma-separated string, whichever the editor produced.
     *
     * @return array<int, string>
     */
    private function list(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('strval', $value);
        }

        $decoded = json_decode((string) $value, true);

        if (is_array($decoded)) {
            return array_map('strval', $decoded);
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($v) => $v !== ''));
    }

    private function contains(mixed $actual, string $needle): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (stripos((string) $item, $needle) !== false) {
                    return true;
                }
            }

            return false;
        }

        return $actual !== null && stripos((string) $actual, $needle) !== false;
    }

    private function isEmpty(mixed $value): bool
    {
        return is_array($value) ? $value === [] : ($value === null || $value === '' || $value === 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $context
     */
    private function execute(Action $action, Task $task, array $context, AutomationRule $rule): void
    {
        $config = $action->config ?? [];
        $reason = "Automation: {$rule->name}";

        match ($action->type) {
            Action::SET_STATUS => $this->setStatus($task, $config, $reason),
            Action::SET_PRIORITY => $this->write($task, ['priority' => $config['priority'] ?? null]),
            Action::ASSIGN => $this->write($task, ['assignee_id' => $this->resolveUserId($config['target'] ?? null, $config['user_id'] ?? null, $task, $context)]),
            Action::SET_DUE_DATE => $this->write($task, ['due_date' => now()->addDays((int) ($config['days'] ?? 0))->toDateString()]),
            Action::ADD_LABEL => $this->addLabel($task, $config),
            Action::ADD_WATCHER => $this->addWatcher($task, $config, $context),
            Action::ADD_COMMENT => $this->addComment($task, $config, $rule),
            Action::NOTIFY => $this->notify($task, $config, $context, $rule),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function setStatus(Task $task, array $config, string $reason): void
    {
        $status = $config['status'] ?? null;

        if (! $status || $task->status === $status) {
            return;
        }

        // Goes through the service so history, notifications and the workflow
        // rules all apply exactly as they would for a person.
        $this->tasks->changeStatus($task, $status, null, null, $reason);
    }

    /**
     * Writes straight to the row. The task events for these fields are not
     * re-dispatched on purpose: an automation that sets a priority should not
     * read as a user edit in the activity feed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function write(Task $task, array $attributes): void
    {
        $attributes = array_filter($attributes, fn ($v) => $v !== null);

        if ($attributes === []) {
            return;
        }

        $task->forceFill($attributes)->save();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function addLabel(Task $task, array $config): void
    {
        $name = trim((string) ($config['label'] ?? ''));

        if ($name === '') {
            return;
        }

        $label = Label::query()->firstOrCreate(
            ['name' => $name],
            ['color' => $config['color'] ?? 'slate'],
        );

        $task->labels()->syncWithoutDetaching([$label->id]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function addWatcher(Task $task, array $config, array $context): void
    {
        $id = $this->resolveUserId($config['target'] ?? null, $config['user_id'] ?? null, $task, $context);

        if ($id) {
            $this->tasks->addWatchers($task, [$id]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function addComment(Task $task, array $config, AutomationRule $rule): void
    {
        $body = trim((string) ($config['body'] ?? ''));

        if ($body === '') {
            return;
        }

        // Authored by the rule, not by a person — user_id stays null so the UI
        // can badge it as automated.
        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => null,
            'body' => $this->interpolate($body, $task, $rule),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function notify(Task $task, array $config, array $context, AutomationRule $rule): void
    {
        $ids = $this->resolveRecipients($config, $task, $context);

        if ($ids === []) {
            return;
        }

        $this->notifications->push($ids, [
            'group' => Notification::GROUP_TASKS,
            'type' => 'automation.notify',
            'title' => $this->interpolate((string) ($config['title'] ?? $rule->name), $task, $rule),
            'body' => $this->interpolate((string) ($config['body'] ?? ''), $task, $rule),
            'icon' => 'zap',
            'link' => route('tasks.show', $task, false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     * @return array<int, int>
     */
    private function resolveRecipients(array $config, Task $task, array $context): array
    {
        // An explicit person wins; the symbolic target is the fallback, and the
        // assignee is the fallback for that.
        $target = $config['target'] ?? (isset($config['user_id']) ? null : Action::TARGET_ASSIGNEE);

        if ($target === Action::TARGET_WATCHERS) {
            return $task->watchers()->pluck('users.id')->all();
        }

        $id = $this->resolveUserId($target, $config['user_id'] ?? null, $task, $context);

        return $id ? [$id] : [];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveUserId(?string $target, mixed $explicit, Task $task, array $context): ?int
    {
        return match ($target) {
            Action::TARGET_REPORTER => $task->created_by_id,
            Action::TARGET_ASSIGNEE => $task->assignee_id,
            Action::TARGET_PROJECT_OWNER => $task->project?->owner_id,
            Action::TARGET_ACTOR => $context['actor_id'] ?? null,
            Action::TARGET_NONE => null,
            default => $explicit ? (int) $explicit : null,
        };
    }

    /**
     * Supports the handful of placeholders a rule author actually needs.
     */
    private function interpolate(string $text, Task $task, AutomationRule $rule): string
    {
        return strtr($text, [
            '{{task.key}}' => $task->key_label ?? (string) $task->id,
            '{{task.title}}' => $task->title,
            '{{task.status}}' => (string) $task->status,
            '{{task.priority}}' => (string) $task->priority,
            '{{task.assignee}}' => $task->assignee?->name ?? 'Unassigned',
            '{{project.name}}' => $task->project?->title ?? '',
            '{{rule.name}}' => $rule->name,
        ]);
    }

    private function record(AutomationRule $rule, Task $task, string $status, ?string $message): void
    {
        AutomationRun::query()->create([
            'automation_rule_id' => $rule->id,
            'task_id' => $task->id,
            'status' => $status,
            'message' => $message ? mb_substr($message, 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}
