<?php

namespace App\Modules\ProjectManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectAttachment;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectAttachmentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();
        if (! Project::visibleTo($user)->whereKey($project->id)->exists()) {
            abort(403);
        }
        if (! $user->hasPermission('projects.update') && $project->owner_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('file');
        $path = $file->store("project-attachments/{$project->id}", 'local');

        $attachment = $project->attachments()->create([
            'uploader_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        Activity::log('project.attachment-added', [
            'module' => 'projects',
            'description' => "Uploaded {$file->getClientOriginalName()} to {$project->title}",
            'properties' => ['project_id' => $project->id, 'attachment_id' => $attachment->id],
        ]);

        return back()->with('status', 'File uploaded.');
    }

    public function download(Request $request, Project $project, ProjectAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        if ($attachment->project_id !== $project->id) {
            abort(404);
        }
        if (! Project::visibleTo($user)->whereKey($project->id)->exists()) {
            abort(403);
        }

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->file_name);
    }

    public function destroy(Request $request, Project $project, ProjectAttachment $attachment): RedirectResponse
    {
        $user = $request->user();
        if ($attachment->project_id !== $project->id) {
            abort(404);
        }
        if (! ($user->hasPermission('projects.update') || $attachment->uploader_id === $user->id || $project->owner_id === $user->id)) {
            abort(403);
        }

        $name = $attachment->file_name;
        $attachmentId = $attachment->id;
        $attachment->deleteFile();
        $attachment->delete();

        Activity::log('project.attachment-removed', [
            'module' => 'projects',
            'description' => "Removed file {$name} from {$project->title}",
            'properties' => ['project_id' => $project->id, 'attachment_id' => $attachmentId],
        ]);

        return back()->with('status', 'File removed.');
    }
}
