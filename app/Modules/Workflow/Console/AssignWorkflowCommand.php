<?php

namespace App\Modules\Workflow\Console;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\Workflow\Models\Workflow;
use Illuminate\Console\Command;

/**
 * Assigns a workflow to one project or to all of them.
 *
 * Stands in for the workflow admin UI, which does not exist yet.
 */
class AssignWorkflowCommand extends Command
{
    protected $signature = 'workflow:assign
                            {workflow? : Workflow name or id}
                            {--project= : Project key or slug (omit to apply to every project)}
                            {--list : Show workflows and which projects use them}';

    protected $description = 'Assign a task workflow to a project (or to every project)';

    public function handle(): int
    {
        if ($this->option('list') || ! $this->argument('workflow')) {
            return $this->listWorkflows();
        }

        $workflow = $this->resolveWorkflow((string) $this->argument('workflow'));

        if (! $workflow) {
            $this->error('No workflow matched that name or id. Run with --list to see the options.');

            return self::FAILURE;
        }

        $projectRef = $this->option('project');

        if ($projectRef) {
            $project = Project::query()
                ->where('key', strtoupper($projectRef))
                ->orWhere('slug', $projectRef)
                ->first();

            if (! $project) {
                $this->error("No project matched \"{$projectRef}\".");

                return self::FAILURE;
            }

            $project->update(['workflow_id' => $workflow->id]);
            $this->info("{$project->key} ({$project->title}) now uses \"{$workflow->name}\".");
        } else {
            $count = Project::query()->update(['workflow_id' => $workflow->id]);
            $this->info("{$count} project(s) now use \"{$workflow->name}\".");
        }

        $this->newLine();
        $this->line('Statuses available: '.$workflow->statuses->pluck('name')->implode(' → '));

        $this->warn(
            'Existing tasks keep their current status. Any status not in the new '.
            'workflow will still render on the board but cannot be moved back into.'
        );

        return self::SUCCESS;
    }

    private function resolveWorkflow(string $ref): ?Workflow
    {
        return Workflow::query()
            ->with('statuses')
            ->when(is_numeric($ref), fn ($q) => $q->whereKey((int) $ref))
            ->when(! is_numeric($ref), fn ($q) => $q->where('name', 'like', "%{$ref}%"))
            ->first();
    }

    private function listWorkflows(): int
    {
        $workflows = Workflow::query()->with('statuses')->withCount('projects')->get();

        $this->table(
            ['ID', 'Name', 'Default', 'Projects', 'Statuses'],
            $workflows->map(fn (Workflow $w) => [
                $w->id,
                $w->name,
                $w->is_default ? 'yes' : '',
                $w->projects_count,
                $w->statuses->pluck('key')->implode(', '),
            ])->all(),
        );

        $this->newLine();
        $this->line('Examples:');
        $this->line('  php artisan workflow:assign "Software delivery" --project=MAP');
        $this->line('  php artisan workflow:assign "Software delivery"        # every project');

        return self::SUCCESS;
    }
}
