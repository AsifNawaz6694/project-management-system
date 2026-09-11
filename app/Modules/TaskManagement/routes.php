<?php

use App\Modules\TaskManagement\Http\Controllers\LabelController;
use App\Modules\TaskManagement\Http\Controllers\SavedFilterController;
use App\Modules\TaskManagement\Http\Controllers\SprintController;
use App\Modules\TaskManagement\Http\Controllers\TaskAttachmentController;
use App\Modules\TaskManagement\Http\Controllers\TaskBulkController;
use App\Modules\TaskManagement\Http\Controllers\TaskCommentController;
use App\Modules\TaskManagement\Http\Controllers\TaskController;
use App\Modules\TaskManagement\Http\Controllers\TaskExportController;
use App\Modules\TaskManagement\Http\Controllers\TaskLinkController;
use App\Modules\TaskManagement\Http\Controllers\TaskWatcherController;
use App\Modules\TaskManagement\Http\Controllers\TimeLogController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

/*
 * Route-level middleware is a coarse first gate. Object-level authorisation is
 * enforced by TaskPolicy inside the controllers and form requests, so a user can
 * never mutate a task they are not allowed to see.
 */
Route::middleware(['auth'])->group(function () {
    // --- collection-level -------------------------------------------------
    Route::get('tasks', [TaskController::class, 'index'])
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.index');

    Route::get('tasks/export', TaskExportController::class)
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.export');

    Route::get('tasks/trash', [TaskController::class, 'trash'])
        ->middleware(EnsurePermission::class.':tasks.delete')
        ->name('tasks.trash');

    Route::get('tasks/search', [TaskLinkController::class, 'search'])
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.search');

    Route::get('tasks/create', [TaskController::class, 'create'])
        ->middleware(EnsurePermission::class.':tasks.create')
        ->name('tasks.create');

    Route::post('tasks', [TaskController::class, 'store'])
        ->middleware(EnsurePermission::class.':tasks.create')
        ->name('tasks.store');

    Route::post('tasks/bulk', TaskBulkController::class)
        ->middleware(EnsurePermission::class.':tasks.bulk-edit')
        ->name('tasks.bulk');

    Route::post('tasks/{task}/restore', [TaskController::class, 'restore'])
        ->middleware(EnsurePermission::class.':tasks.delete')
        ->withTrashed()
        ->name('tasks.restore');

    // --- saved filters ----------------------------------------------------
    Route::post('task-filters', [SavedFilterController::class, 'store'])->name('task-filters.store');
    Route::delete('task-filters/{savedFilter}', [SavedFilterController::class, 'destroy'])->name('task-filters.destroy');

    // --- labels -----------------------------------------------------------
    Route::post('labels', [LabelController::class, 'store'])->name('labels.store');
    Route::patch('labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

    // --- single task ------------------------------------------------------
    Route::get('tasks/{task}', [TaskController::class, 'show'])
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.show');

    Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])
        ->middleware(EnsurePermission::class.':tasks.update')
        ->name('tasks.edit');

    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/status', [TaskController::class, 'changeStatus'])->name('tasks.status');

    Route::post('tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
    Route::post('tasks/{task}/unarchive', [TaskController::class, 'unarchive'])->name('tasks.unarchive');

    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':tasks.delete')
        ->name('tasks.destroy');

    // --- watchers ---------------------------------------------------------
    Route::post('tasks/{task}/watch', [TaskWatcherController::class, 'toggle'])->name('tasks.watch');

    // --- links ------------------------------------------------------------
    Route::post('tasks/{task}/links', [TaskLinkController::class, 'store'])->name('tasks.links.store');
    Route::delete('tasks/{task}/links/{link}', [TaskLinkController::class, 'destroy'])->name('tasks.links.destroy');

    // --- comments ---------------------------------------------------------
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::patch('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'update'])->name('tasks.comments.update');
    Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // --- attachments ------------------------------------------------------
    Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::get('tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'download'])->name('tasks.attachments.download');
    Route::delete('tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy');

    // --- time tracking ----------------------------------------------------
    Route::post('tasks/{task}/time-logs', [TimeLogController::class, 'store'])
        ->middleware(EnsurePermission::class.':tasks.log-time')
        ->name('tasks.time-logs.store');
    Route::delete('tasks/{task}/time-logs/{timeLog}', [TimeLogController::class, 'destroy'])
        ->name('tasks.time-logs.destroy');

    /*
    |----------------------------------------------------------------------
    | Sprints & backlog (project-scoped; {project} binds by slug)
    |----------------------------------------------------------------------
    */
    Route::get('projects/{project}/backlog', [SprintController::class, 'index'])->name('sprints.backlog');
    Route::get('projects/{project}/sprint-report', [SprintController::class, 'report'])->name('sprints.report');

    Route::post('projects/{project}/sprints', [SprintController::class, 'store'])->name('sprints.store');
    Route::patch('projects/{project}/sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update');
    Route::post('projects/{project}/sprints/{sprint}/start', [SprintController::class, 'start'])->name('sprints.start');
    Route::post('projects/{project}/sprints/{sprint}/complete', [SprintController::class, 'complete'])->name('sprints.complete');
    Route::delete('projects/{project}/sprints/{sprint}', [SprintController::class, 'destroy'])->name('sprints.destroy');

    Route::post('projects/{project}/backlog/assign', [SprintController::class, 'assign'])->name('sprints.assign');
    Route::post('projects/{project}/backlog/estimate', [SprintController::class, 'estimate'])->name('sprints.estimate');
});
