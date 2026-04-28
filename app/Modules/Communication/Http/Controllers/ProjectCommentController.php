<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Models\ProjectComment;
use App\Modules\Communication\Services\MentionParser;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectCommentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();
        if (! Project::visibleTo($user)->whereKey($project->id)->exists()) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:project_comments,id'],
        ]);

        $mentions = MentionParser::extract($data['body']);

        $comment = ProjectComment::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'mentions' => $mentions,
        ]);

        Activity::log('project.commented', [
            'module' => 'projects',
            'description' => "Commented on {$project->title}",
            'properties' => ['project_id' => $project->id, 'comment_id' => $comment->id],
        ]);

        if (! empty($mentions)) {
            MentionParser::notify(
                $mentions,
                "{$user->name} mentioned you on project \"{$project->title}\"",
                ['project_id' => $project->id, 'project_slug' => $project->slug, 'comment_id' => $comment->id],
                $user->id,
            );
        }

        return back();
    }

    public function destroy(Request $request, Project $project, ProjectComment $comment): RedirectResponse
    {
        $user = $request->user();
        if ($comment->project_id !== $project->id) {
            abort(404);
        }
        if ($comment->user_id !== $user->id && ! $user->hasPermission('projects.update')) {
            abort(403);
        }

        $commentId = $comment->id;
        $comment->delete();

        Activity::log('project.comment-deleted', [
            'module' => 'projects',
            'description' => "Deleted a comment on project \"{$project->title}\"",
            'properties' => ['project_id' => $project->id, 'comment_id' => $commentId],
        ]);

        return back();
    }
}
