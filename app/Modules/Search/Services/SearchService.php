<?php

namespace App\Modules\Search\Services;

use App\Models\User;
use App\Modules\Feedback\Models\FeedbackCycle;
use App\Modules\MeetingManagement\Models\Meeting;
use App\Modules\Okrs\Models\Objective;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use App\Modules\TaskManagement\Models\TaskComment;
use App\Modules\Teams\Models\Team;

/**
 * One question asked of every entity the user can see.
 *
 * Each source is a separate small query rather than a UNION: they have nothing
 * in common but a title, the visibility rules differ per entity, and MySQL 5.7
 * gives us no window functions to rank a union sensibly anyway.
 *
 * Every source is responsible for its own scoping — a search result must never
 * be the way someone discovers a record they cannot open.
 *
 * Matching is LIKE '%term%' rather than a FULLTEXT index, deliberately. InnoDB
 * defers full-text index updates until commit, so MATCH … AGAINST returns
 * nothing for rows written inside an open transaction — which is every row the
 * test suite creates. Adopting it would mean search behaved differently under
 * test than in production, and would trade substring matching for word-prefix
 * matching. Revisit if a workspace ever grows large enough for the scan to hurt.
 */
class SearchService
{
    /** Per-section cap. The palette shows a few of each, not everything. */
    public const PER_SECTION = 6;

    /**
     * @return array<string, mixed>
     */
    public function search(User $user, string $term, int $limit = self::PER_SECTION): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return ['term' => $term, 'sections' => [], 'total' => 0];
        }

        $sections = array_filter([
            $this->tasks($user, $term, $limit),
            $this->projects($user, $term, $limit),
            $this->comments($user, $term, $limit),
            $this->people($user, $term, $limit),
            $this->teams($user, $term, $limit),
            $this->meetings($user, $term, $limit),
            $this->objectives($user, $term, $limit),
            $this->feedback($user, $term, $limit),
        ], fn ($section) => $section !== null && $section['items'] !== []);

        return [
            'term' => $term,
            'sections' => array_values($sections),
            'total' => array_sum(array_map(fn ($s) => count($s['items']), $sections)),
        ];
    }

    private function like(string $term): string
    {
        // Escape the wildcards so a term containing % or _ still means itself.
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tasks(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('tasks.view')) {
            return null;
        }

        $like = $this->like($term);

        $items = Task::query()
            ->visibleTo($user)
            ->notArchived()
            ->where(function ($q) use ($like, $term) {
                $q->where('tasks.title', 'like', $like)
                    ->orWhere('tasks.description', 'like', $like);

                // WEB-142 or a bare number should find the task by key.
                if (preg_match('/^([A-Za-z][A-Za-z0-9]*)-(\d+)$/', $term, $m)) {
                    $q->orWhere(function ($k) use ($m) {
                        $k->where('tasks.number', (int) $m[2])
                            ->whereExists(fn ($p) => $p->selectRaw('1')->from('projects')
                                ->whereColumn('projects.id', 'tasks.project_id')
                                ->where('projects.key', strtoupper($m[1])));
                    });
                } elseif (ctype_digit($term)) {
                    $q->orWhere('tasks.number', (int) $term);
                }
            })
            ->with('project:id,key,title')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'status', 'priority', 'number', 'project_id'])
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'subtitle' => trim(($t->key_label ?? '').' · '.($t->project?->title ?? '')),
                'url' => route('tasks.show', $t->id, false),
            ]);

        return ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'list-checks', 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function projects(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('projects.view')) {
            return null;
        }

        $like = $this->like($term);

        $items = Project::query()
            ->visibleTo($user)
            ->where(fn ($q) => $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('key', 'like', $like))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'slug', 'key', 'title', 'status'])
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'subtitle' => trim(($p->key ?? '').' · '.$p->status),
                'url' => route('projects.show', $p->slug, false),
            ]);

        return ['key' => 'projects', 'label' => 'Projects', 'icon' => 'folder-kanban', 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function comments(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('tasks.view')) {
            return null;
        }

        $items = TaskComment::query()
            ->where('body', 'like', $this->like($term))
            // A comment is only visible if its task is.
            ->whereIn('task_id', Task::query()->visibleTo($user)->select('tasks.id'))
            ->with('task:id,title,number,project_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'task_id', 'body'])
            ->map(fn (TaskComment $c) => [
                'id' => $c->id,
                'title' => str($c->body)->limit(80)->toString(),
                'subtitle' => 'on '.($c->task?->title ?? 'a task'),
                'url' => $c->task_id ? route('tasks.show', $c->task_id, false) : '#',
            ]);

        return ['key' => 'comments', 'label' => 'Comments', 'icon' => 'message-square-text', 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function people(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('users.view')) {
            return null;
        }

        $like = $this->like($term);

        $items = User::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('job_title', 'like', $like))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'job_title'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'title' => $u->name,
                'subtitle' => $u->job_title ?: $u->email,
                'url' => route('users.show', $u->id, false),
            ]);

        return ['key' => 'people', 'label' => 'People', 'icon' => 'users', 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function teams(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('teams.view')) {
            return null;
        }

        $items = Team::query()
            ->where('name', 'like', $this->like($term))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'slug', 'name', 'description'])
            ->map(fn (Team $t) => [
                'id' => $t->id,
                'title' => $t->name,
                'subtitle' => str((string) $t->description)->limit(60)->toString(),
                'url' => route('teams.show', $t->slug ?: $t->id, false),
            ]);

        return ['key' => 'teams', 'label' => 'Teams', 'icon' => 'users-round', 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function meetings(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('meetings.view')) {
            return null;
        }

        $items = Meeting::query()
            ->where('title', 'like', $this->like($term))
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get(['id', 'title', 'kind', 'starts_at'])
            ->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'subtitle' => $m->starts_at?->format('D j M, H:i') ?? $m->kind,
                'url' => route('meetings.show', $m->id, false),
            ]);

        return ['key' => 'meetings', 'label' => 'Meetings', 'icon' => 'calendar-clock', 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function objectives(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('okrs.view')) {
            return null;
        }

        $items = Objective::visibleTo($user)
            ->where('title', 'like', $this->like($term))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'period', 'progress'])
            ->map(fn (Objective $o) => [
                'id' => $o->id,
                'title' => $o->title,
                'subtitle' => $o->period.' · '.$o->progress.'%',
                'url' => route('okrs.show', $o->id, false),
            ]);

        return ['key' => 'objectives', 'label' => 'Objectives', 'icon' => 'target', 'items' => $items->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function feedback(User $user, string $term, int $limit): ?array
    {
        if (! $user->hasPermission('feedback.view')) {
            return null;
        }

        $items = FeedbackCycle::visibleTo($user)
            ->where('name', 'like', $this->like($term))
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get(['id', 'name', 'kind', 'status'])
            ->map(fn (FeedbackCycle $c) => [
                'id' => $c->id,
                'title' => $c->name,
                'subtitle' => $c->kind.' · '.$c->status,
                'url' => route('feedback.cycles.show', $c->id, false),
            ]);

        return ['key' => 'feedback', 'label' => 'Feedback', 'icon' => 'message-square-text', 'items' => $items->all()];
    }
}
