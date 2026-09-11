<?php

namespace Database\Seeders;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\TaskType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStatus;
use App\Modules\Workflow\Models\WorkflowTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the workflows and the task type catalogue.
 * Idempotent — safe to re-run to pick up new statuses or transitions.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTaskTypes();

        $pipeline = $this->seedSoftwareDelivery();

        $this->retireGenericWorkflow($pipeline);

        Project::query()->whereNull('workflow_id')->update(['workflow_id' => $pipeline->id]);
    }

    private function seedTaskTypes(): void
    {
        $types = [
            ['key' => 'task', 'name' => 'Task', 'icon' => 'circle-check', 'color' => 'blue', 'position' => 1],
            ['key' => 'bug', 'name' => 'Bug', 'icon' => 'bug', 'color' => 'rose', 'position' => 2],
            ['key' => 'story', 'name' => 'Story', 'icon' => 'bookmark', 'color' => 'emerald', 'position' => 3],
            ['key' => 'epic', 'name' => 'Epic', 'icon' => 'zap', 'color' => 'violet', 'position' => 4],
            ['key' => 'improvement', 'name' => 'Improvement', 'icon' => 'trending-up', 'color' => 'sky', 'position' => 5],
            ['key' => 'subtask', 'name' => 'Sub-task', 'icon' => 'git-branch', 'color' => 'slate', 'position' => 6, 'is_subtask_type' => true],
        ];

        foreach ($types as $type) {
            TaskType::query()->updateOrCreate(['key' => $type['key']], $type);
        }
    }

    /**
     * Dev → code review → QA → deployment pipeline.
     *
     * The two QA verdict transitions require a written reason, which is stored
     * on the status-history row so the QA trail is queryable.
     */
    private function seedSoftwareDelivery(): Workflow
    {
        $workflow = $this->seedWorkflow('Software delivery', 'Development, QA verdict, review and deployment.', true, [
            ['key' => 'todo', 'name' => 'To do', 'category' => 'todo', 'color' => 'slate', 'is_initial' => true],
            ['key' => 'in_progress', 'name' => 'Dev In progress', 'category' => 'in_progress', 'color' => 'amber'],
            ['key' => 'pushed_for_qa', 'name' => 'Pushed for QA', 'category' => 'in_progress', 'color' => 'sky'],
            ['key' => 'qa_in_progress', 'name' => 'QA In progress', 'category' => 'in_progress', 'color' => 'violet'],
            ['key' => 'review', 'name' => 'Review', 'category' => 'in_progress', 'color' => 'blue'],
            ['key' => 'ready_for_deployment', 'name' => 'Ready for deployment', 'category' => 'in_progress', 'color' => 'blue'],
            ['key' => 'deployed', 'name' => 'Deployed', 'category' => 'done', 'color' => 'emerald'],
        ]);

        // Drop stages that are no longer part of the pipeline first, so their
        // transitions go with them.
        $this->pruneStatuses($workflow, [
            'todo', 'in_progress', 'pushed_for_qa', 'qa_in_progress',
            'review', 'ready_for_deployment', 'deployed',
        ], 'in_progress');

        $byKey = $workflow->fresh('statuses')->statuses->keyBy('key');

        // [from, to, requires_comment, comment_label]
        $edges = [
            ['todo', 'in_progress', false, null],
            ['in_progress', 'pushed_for_qa', false, null],
            ['pushed_for_qa', 'qa_in_progress', false, null],

            // The QA verdict. Passing sends it to Review, failing sends it back
            // to development — both demand a written reason.
            ['qa_in_progress', 'review', true, 'QA passed — what was verified? Note the scope of testing.'],
            ['qa_in_progress', 'in_progress', true, 'QA failed — what went wrong? Include steps to reproduce.'],

            ['review', 'ready_for_deployment', false, null],
            // Review outcomes other than approval, each needing a written note.
            ['review', 'in_progress', true, 'Sending back to development — what needs changing?'],
            ['review', 'qa_in_progress', true, 'Sending back to QA — what should be re-tested?'],
            ['ready_for_deployment', 'deployed', false, null],
        ];

        // Replace the rule set so removed edges do not linger.
        WorkflowTransition::query()->where('workflow_id', $workflow->id)->delete();

        foreach ($edges as [$from, $to, $requires, $label]) {
            if (! isset($byKey[$from], $byKey[$to])) {
                continue;
            }

            WorkflowTransition::query()->create([
                'workflow_id' => $workflow->id,
                'from_status_id' => $byKey[$from]->id,
                'to_status_id' => $byKey[$to]->id,
                'name' => $byKey[$to]->name,
                'requires_comment' => $requires,
                'comment_label' => $label,
            ]);
        }

        return $workflow;
    }

    /**
     * Retire the old generic workflow.
     *
     * It existed only to provide a three-column board and was the last place the
     * "completed" stage lived. It is removed when nothing references it; if a
     * project somehow still points at it, that project is moved onto the
     * pipeline first so no task is stranded.
     */
    private function retireGenericWorkflow(Workflow $pipeline): void
    {
        $generic = Workflow::query()->where('name', 'Simple')->first();

        if (! $generic || $generic->is($pipeline)) {
            return;
        }

        $doneKey = $pipeline->statuses()->where('category', WorkflowStatus::CATEGORY_DONE)->value('key') ?? 'deployed';
        $initialKey = $pipeline->statuses()->where('is_initial', true)->value('key') ?? 'todo';

        $projectIds = Project::query()->where('workflow_id', $generic->id)->pluck('id');

        if ($projectIds->isNotEmpty()) {
            // Map the retired stages onto their pipeline equivalents.
            DB::table('tasks')->whereIn('project_id', $projectIds)
                ->where('status', 'completed')->update(['status' => $doneKey]);

            DB::table('tasks')->whereIn('project_id', $projectIds)
                ->whereNotIn('status', $pipeline->statuses()->pluck('key'))
                ->update(['status' => $initialKey]);

            Project::query()->whereIn('id', $projectIds)->update(['workflow_id' => $pipeline->id]);
        }

        $generic->delete();
    }

    /**
     * Remove stages that are no longer part of the workflow.
     *
     * Any task still sitting on a removed stage is moved to $fallback first, so
     * a pipeline change never strands live work on a status that no longer
     * exists. Both are chosen to preserve meaning where possible.
     *
     * @param  array<int, string>  $keep
     */
    private function pruneStatuses(Workflow $workflow, array $keep, string $fallback): void
    {
        $stale = WorkflowStatus::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotIn('key', $keep)
            ->get();

        if ($stale->isEmpty()) {
            return;
        }

        $projectIds = Project::query()->where('workflow_id', $workflow->id)->pluck('id');

        foreach ($stale as $status) {
            // "completed" used to be the terminal stage; "deployed" is now.
            $target = $status->category === WorkflowStatus::CATEGORY_DONE ? 'deployed' : $fallback;

            if ($projectIds->isNotEmpty()) {
                DB::table('tasks')
                    ->whereIn('project_id', $projectIds)
                    ->where('status', $status->key)
                    ->update(['status' => $target]);
            }

            WorkflowTransition::query()
                ->where('workflow_id', $workflow->id)
                ->where(fn ($q) => $q->where('from_status_id', $status->id)->orWhere('to_status_id', $status->id))
                ->delete();

            $status->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $statuses
     */
    private function seedWorkflow(string $name, string $description, bool $isDefault, array $statuses): Workflow
    {
        $workflow = Workflow::query()->updateOrCreate(
            ['name' => $name],
            ['description' => $description, 'is_default' => $isDefault, 'is_system' => true],
        );

        foreach ($statuses as $i => $status) {
            WorkflowStatus::query()->updateOrCreate(
                ['workflow_id' => $workflow->id, 'key' => $status['key']],
                [
                    'name' => $status['name'],
                    'category' => $status['category'],
                    'color' => $status['color'],
                    'position' => $i + 1,
                    'is_initial' => $status['is_initial'] ?? false,
                ],
            );
        }

        return $workflow->fresh('statuses');
    }
}
