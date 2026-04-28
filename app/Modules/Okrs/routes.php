<?php

use App\Modules\Okrs\Http\Controllers\ObjectiveController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('okrs', [ObjectiveController::class, 'index'])
        ->middleware(EnsurePermission::class.':okrs.view')
        ->name('okrs.index');

    Route::get('okrs/create', [ObjectiveController::class, 'create'])
        ->middleware(EnsurePermission::class.':okrs.create')
        ->name('okrs.create');

    Route::post('okrs', [ObjectiveController::class, 'store'])
        ->middleware(EnsurePermission::class.':okrs.create')
        ->name('okrs.store');

    Route::get('okrs/{objective}', [ObjectiveController::class, 'show'])
        ->middleware(EnsurePermission::class.':okrs.view')
        ->name('okrs.show');

    Route::get('okrs/{objective}/edit', [ObjectiveController::class, 'edit'])
        ->name('okrs.edit');

    Route::patch('okrs/{objective}', [ObjectiveController::class, 'update'])
        ->name('okrs.update');

    Route::delete('okrs/{objective}', [ObjectiveController::class, 'destroy'])
        ->name('okrs.destroy');

    Route::post('okrs/{objective}/key-results/{keyResult}/updates', [ObjectiveController::class, 'recordUpdate'])
        ->name('okrs.key-results.updates');
});
