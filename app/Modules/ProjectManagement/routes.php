<?php

use App\Modules\ProjectManagement\Http\Controllers\MilestoneController;
use App\Modules\ProjectManagement\Http\Controllers\ProjectController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('projects', [ProjectController::class, 'index'])
        ->middleware(EnsurePermission::class.':projects.view')
        ->name('projects.index');

    Route::get('projects/create', [ProjectController::class, 'create'])
        ->middleware(EnsurePermission::class.':projects.create')
        ->name('projects.create');

    Route::post('projects', [ProjectController::class, 'store'])
        ->middleware(EnsurePermission::class.':projects.create')
        ->name('projects.store');

    Route::get('projects/{project}', [ProjectController::class, 'show'])
        ->middleware(EnsurePermission::class.':projects.view')
        ->name('projects.show');

    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])
        ->middleware(EnsurePermission::class.':projects.update')
        ->name('projects.edit');

    Route::patch('projects/{project}', [ProjectController::class, 'update'])
        ->middleware(EnsurePermission::class.':projects.update')
        ->name('projects.update');

    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':projects.delete')
        ->name('projects.destroy');

    Route::patch('projects/{project}/milestones/{milestone}/toggle', [MilestoneController::class, 'toggle'])
        ->name('projects.milestones.toggle');
});
