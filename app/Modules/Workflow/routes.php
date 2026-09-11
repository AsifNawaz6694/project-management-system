<?php

use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use App\Modules\Workflow\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('workflows', [WorkflowController::class, 'index'])
        ->middleware(EnsurePermission::class.':workflows.view')
        ->name('workflows.index');

    Route::middleware(EnsurePermission::class.':workflows.manage')->group(function () {
        Route::post('workflows', [WorkflowController::class, 'store'])->name('workflows.store');
        Route::get('workflows/{workflow}/edit', [WorkflowController::class, 'edit'])->name('workflows.edit');
        Route::patch('workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflows.update');
        Route::delete('workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflows.destroy');

        Route::post('workflows/{workflow}/statuses', [WorkflowController::class, 'storeStatus'])->name('workflows.statuses.store');
        Route::patch('workflows/{workflow}/statuses/{status}', [WorkflowController::class, 'updateStatus'])->name('workflows.statuses.update');
        Route::delete('workflows/{workflow}/statuses/{status}', [WorkflowController::class, 'destroyStatus'])->name('workflows.statuses.destroy');
        Route::post('workflows/{workflow}/statuses/reorder', [WorkflowController::class, 'reorderStatuses'])->name('workflows.statuses.reorder');

        Route::put('workflows/{workflow}/transitions', [WorkflowController::class, 'updateTransitions'])->name('workflows.transitions.update');
        Route::post('workflows/{workflow}/projects', [WorkflowController::class, 'assignProjects'])->name('workflows.projects.assign');
        Route::post('workflows/{workflow}/people', [WorkflowController::class, 'assignPeople'])->name('workflows.people.assign');
        Route::post('workflows/{workflow}/teams', [WorkflowController::class, 'assignTeams'])->name('workflows.teams.assign');
    });
});
