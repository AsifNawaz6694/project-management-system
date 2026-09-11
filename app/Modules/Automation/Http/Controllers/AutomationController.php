<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Automation\Models\AutomationAction as Action;
use App\Modules\Automation\Models\AutomationCondition as Condition;
use App\Modules\Automation\Models\AutomationRule;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Label;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\Workflow\Models\WorkflowStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $rules = AutomationRule::query()
            ->with('project:id,title')
            ->withCount(['conditions', 'actions'])
            ->orderBy('run_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('automations/index', [
            'rules' => $rules->map(fn (AutomationRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'trigger' => $r->trigger,
                'is_active' => $r->is_active,
                'project' => $r->project?->title,
                'conditions_count' => $r->conditions_count,
                'actions_count' => $r->actions_count,
                'run_count' => $r->run_count,
                'last_run_at' => $r->last_run_at?->toIso8601String(),
            ]),
            'triggers' => AutomationRule::TRIGGERS,
            'can' => ['manage' => $user->isAdmin() || $user->hasPermission('automations.manage')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'trigger' => ['required', Rule::in(AutomationRule::TRIGGERS)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $rule = AutomationRule::query()->create($data + [
            'is_active' => false, // Never live until its actions are configured.
            'created_by_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('automations.edit', $rule)
            ->with('status', 'Rule created. Add conditions and actions, then switch it on.');
    }

    public function edit(AutomationRule $automation): Response
    {
        $automation->load(['conditions', 'actions']);

        return Inertia::render('automations/edit', [
            'rule' => [
                'id' => $automation->id,
                'name' => $automation->name,
                'description' => $automation->description,
                'trigger' => $automation->trigger,
                'project_id' => $automation->project_id,
                'is_active' => $automation->is_active,
                'run_order' => $automation->run_order,
                'run_count' => $automation->run_count,
                'last_run_at' => $automation->last_run_at?->toIso8601String(),
            ],
            'conditions' => $automation->conditions->map(fn (Condition $c) => [
                'field' => $c->field,
                'operator' => $c->operator,
                'value' => $c->value,
            ])->values(),
            'actions' => $automation->actions->map(fn (Action $a) => [
                'type' => $a->type,
                'config' => $a->config ?? [],
            ])->values(),
            'triggers' => AutomationRule::TRIGGERS,
            'fields' => Condition::FIELDS,
            'operators' => Condition::OPERATORS,
            'unaryOperators' => Condition::UNARY_OPERATORS,
            'actionTypes' => Action::TYPES,
            'priorities' => Task::PRIORITIES,
            'statuses' => WorkflowStatus::query()
                ->select('key', 'name')
                ->distinct()
                ->orderBy('name')
                ->get()
                ->map(fn (WorkflowStatus $s) => ['value' => $s->key, 'label' => $s->name])
                ->unique('value')
                ->values(),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Project $p) => ['value' => (string) $p->id, 'label' => $p->title]),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name]),
            'labels' => Label::query()->orderBy('name')->pluck('name'),
            'runs' => AutomationRun::query()
                ->where('automation_rule_id', $automation->id)
                ->latest('created_at')
                ->limit(25)
                ->get(['id', 'task_id', 'status', 'message', 'created_at'])
                ->map(fn (AutomationRun $r) => [
                    'id' => $r->id,
                    'task_id' => $r->task_id,
                    'status' => $r->status,
                    'message' => $r->message,
                    'created_at' => $r->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request, AutomationRule $automation): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:255'],
            'trigger' => ['required', Rule::in(AutomationRule::TRIGGERS)],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'is_active' => ['boolean'],
            'run_order' => ['integer', 'min:0', 'max:999'],
        ]);

        $automation->update($data);

        return back()->with('status', 'Rule saved.');
    }

    /**
     * Replaces the whole condition + action set in one transaction, so a rule
     * is never left half-configured and firing.
     */
    public function updateLogic(Request $request, AutomationRule $automation): RedirectResponse
    {
        $data = $request->validate([
            'conditions' => ['present', 'array'],
            'conditions.*.field' => ['required', Rule::in(Condition::FIELDS)],
            'conditions.*.operator' => ['required', Rule::in(Condition::OPERATORS)],
            'conditions.*.value' => ['nullable', 'string', 'max:500'],
            'actions' => ['present', 'array'],
            'actions.*.type' => ['required', Rule::in(Action::TYPES)],
            'actions.*.config' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($automation, $data) {
            $automation->conditions()->delete();
            $automation->actions()->delete();

            foreach (array_values($data['conditions']) as $i => $condition) {
                // A binary operator with no value can never be satisfied, so it
                // is dropped rather than silently failing every run.
                $unary = in_array($condition['operator'], Condition::UNARY_OPERATORS, true);
                $value = $condition['value'] ?? null;

                if (! $unary && ($value === null || $value === '')) {
                    continue;
                }

                $automation->conditions()->create([
                    'field' => $condition['field'],
                    'operator' => $condition['operator'],
                    'value' => $unary ? null : $value,
                    'position' => $i,
                ]);
            }

            foreach (array_values($data['actions']) as $i => $action) {
                $automation->actions()->create([
                    'type' => $action['type'],
                    'config' => $action['config'] ?? [],
                    'position' => $i,
                ]);
            }
        });

        return back()->with('status', 'Logic saved.');
    }

    public function toggle(AutomationRule $automation): RedirectResponse
    {
        $automation->forceFill(['is_active' => ! $automation->is_active])->save();

        return back()->with('status', $automation->is_active ? 'Rule switched on.' : 'Rule switched off.');
    }

    public function destroy(AutomationRule $automation): RedirectResponse
    {
        $automation->delete();

        return redirect()->route('automations.index')->with('status', 'Rule deleted.');
    }
}
