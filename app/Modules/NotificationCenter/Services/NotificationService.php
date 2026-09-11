<?php

namespace App\Modules\NotificationCenter\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(private readonly NotificationDelivery $delivery) {}

    /**
     * How long an unread notification stays open to collapsing. Beyond this a
     * new event starts a fresh row, so an old unread item is not silently
     * rewritten days later.
     */
    private const COLLAPSE_WINDOW_HOURS = 24;

    /**
     * Push a notification to one or many users.
     *
     * When an unread notification of the same type already exists for the same
     * entity, it is bumped rather than duplicated — twelve comments on a task
     * become one "12 new comments" row. This keeps the unread badge a count of
     * things needing attention rather than a count of raw events.
     *
     * @param  int|array<int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function push(int|array $userIds, array $payload, ?int $actorId = null): void
    {
        $ids = array_filter(array_unique((array) $userIds));

        if ($actorId !== null) {
            // Never notify someone about their own action.
            $ids = array_values(array_diff($ids, [$actorId]));
        }

        if ($ids === []) {
            return;
        }

        $entityKey = $payload['entity_key'] ?? $this->deriveEntityKey($payload);
        $collapse = $payload['collapse'] ?? true;
        $group = $payload['group'] ?? Notification::GROUP_SYSTEM;

        // Drop recipients who muted this group in-app. Email is decided
        // separately, so a user can keep email while silencing the bell.
        $recipients = User::query()->whereIn('id', $ids)->get();
        $ids = $recipients
            ->filter(fn (User $u) => $this->delivery->wantsInApp($u, $group))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids, $payload, $actorId, $entityKey, $collapse) {
            $fresh = [];

            foreach ($ids as $userId) {
                if ($collapse && $entityKey && $this->bumpExisting((int) $userId, $payload, $entityKey, $actorId)) {
                    continue;
                }

                $fresh[] = [
                    'user_id' => $userId,
                    'actor_id' => $actorId,
                    'group' => $payload['group'] ?? Notification::GROUP_SYSTEM,
                    'type' => $payload['type'],
                    'entity_key' => $entityKey,
                    'event_count' => 1,
                    'title' => $payload['title'],
                    'body' => $payload['body'] ?? null,
                    'icon' => $payload['icon'] ?? null,
                    'tone' => $payload['tone'] ?? 'blue',
                    'link' => $payload['link'] ?? null,
                    'data' => isset($payload['data']) ? json_encode($payload['data']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($fresh !== []) {
                Notification::query()->insert($fresh);
            }
        });

        // Delivery happens after the records exist, so a mail failure can never
        // roll back the notification itself.
        $this->deliverEmails($ids, $payload, $entityKey);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function deliverEmails(array $userIds, array $payload, ?string $entityKey): void
    {
        $group = $payload['group'] ?? Notification::GROUP_SYSTEM;

        $users = User::query()->whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            if (! $this->delivery->wantsEmail($user, $group)) {
                continue;
            }

            $notification = Notification::query()
                ->where('user_id', $user->id)
                ->where('type', $payload['type'])
                ->when($entityKey, fn ($q) => $q->where('entity_key', $entityKey))
                ->whereNull('read_at')
                ->latest('id')
                ->first();

            if ($notification) {
                $this->delivery->emailNow($user, $notification);
            }
        }
    }

    /**
     * Bump an existing unread notification instead of adding another.
     *
     * @return bool True when an existing row absorbed the event.
     */
    private function bumpExisting(int $userId, array $payload, string $entityKey, ?int $actorId): bool
    {
        $existing = Notification::query()
            ->where('user_id', $userId)
            ->where('type', $payload['type'])
            ->where('entity_key', $entityKey)
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subHours(self::COLLAPSE_WINDOW_HOURS))
            ->orderByDesc('id')
            ->first();

        if (! $existing) {
            return false;
        }

        $count = $existing->event_count + 1;

        $existing->forceFill([
            'event_count' => $count,
            'actor_id' => $actorId ?? $existing->actor_id,
            // Keep the latest wording, and say how many events it represents.
            'title' => $payload['title'],
            'body' => $payload['body'] ?? $existing->body,
            'link' => $payload['link'] ?? $existing->link,
            'data' => isset($payload['data']) ? $payload['data'] : $existing->data,
            // Resurface it in the list.
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Infer the entity a notification is about from its data payload.
     */
    private function deriveEntityKey(array $payload): ?string
    {
        $data = $payload['data'] ?? [];

        foreach (['task_id' => 'task', 'project_id' => 'project',
            'meeting_id' => 'meeting', 'objective_id' => 'objective', 'feedback_request_id' => 'feedback'] as $field => $prefix) {
            if (! empty($data[$field])) {
                return $prefix.':'.$data[$field];
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ reading

    public function markRead(User $user, ?int $id = null): void
    {
        $query = Notification::query()->where('user_id', $user->id)->whereNull('read_at');

        if ($id !== null) {
            $query->where('id', $id);
        }

        $query->update(['read_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Clear everything about one entity — called when the user actually opens
     * the task or project, so reading the thing counts as reading its alerts.
     */
    public function markEntityRead(User $user, string $entityKey): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('entity_key', $entityKey)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Unread totals per group, for the badge breakdown.
     *
     * @return array<string, int>
     */
    public function unreadByGroup(User $user): array
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->groupBy('group')
            // Let the builder quote "group" per driver — it is a reserved word.
            ->select('group', DB::raw('COUNT(*) as aggregate'))
            ->pluck('aggregate', 'group')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    // ------------------------------------------------------------------ clearing

    public function clear(User $user, int $id): void
    {
        Notification::query()->where('user_id', $user->id)->where('id', $id)->delete();
    }

    public function clearAll(User $user): void
    {
        Notification::query()->where('user_id', $user->id)->delete();
    }

    /**
     * Housekeeping: drop read notifications older than the retention window so
     * the table does not grow without bound.
     */
    public function pruneRead(int $days = 60): int
    {
        return Notification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($days))
            ->delete();
    }
}
