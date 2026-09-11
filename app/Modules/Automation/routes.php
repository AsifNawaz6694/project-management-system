<?php

use App\Modules\Automation\Http\Controllers\AutomationController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('automations', [AutomationController::class, 'index'])
        ->middleware(EnsurePermission::class.':automations.view')
        ->name('automations.index');

    Route::middleware(EnsurePermission::class.':automations.manage')->group(function () {
        Route::post('automations', [AutomationController::class, 'store'])->name('automations.store');
        Route::get('automations/{automation}/edit', [AutomationController::class, 'edit'])->name('automations.edit');
        Route::patch('automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
        Route::put('automations/{automation}/logic', [AutomationController::class, 'updateLogic'])->name('automations.logic.update');
        Route::post('automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
        Route::delete('automations/{automation}', [AutomationController::class, 'destroy'])->name('automations.destroy');
    });
});
