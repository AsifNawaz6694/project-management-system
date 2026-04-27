<?php

use App\Modules\TaskManagement\Http\Controllers\TaskAttachmentController;
use App\Modules\TaskManagement\Http\Controllers\TaskCommentController;
use App\Modules\TaskManagement\Http\Controllers\TaskController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('tasks', [TaskController::class, 'index'])
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.index');

    Route::get('tasks/create', [TaskController::class, 'create'])
        ->middleware(EnsurePermission::class.':tasks.create')
        ->name('tasks.create');

    Route::post('tasks', [TaskController::class, 'store'])
        ->middleware(EnsurePermission::class.':tasks.create')
        ->name('tasks.store');

    Route::get('tasks/{task}', [TaskController::class, 'show'])
        ->middleware(EnsurePermission::class.':tasks.view')
        ->name('tasks.show');

    Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])
        ->middleware(EnsurePermission::class.':tasks.update')
        ->name('tasks.edit');

    Route::patch('tasks/{task}', [TaskController::class, 'update'])
        ->name('tasks.update');

    Route::patch('tasks/{task}/status', [TaskController::class, 'changeStatus'])
        ->name('tasks.status');

    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':tasks.delete')
        ->name('tasks.destroy');

    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::get('tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'download'])->name('tasks.attachments.download');
    Route::delete('tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy');
});
