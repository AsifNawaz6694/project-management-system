<?php

use App\Modules\Teams\Http\Controllers\TeamController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('teams', [TeamController::class, 'index'])
        ->middleware(EnsurePermission::class.':teams.view')
        ->name('teams.index');

    Route::post('teams', [TeamController::class, 'store'])
        ->middleware(EnsurePermission::class.':teams.manage')
        ->name('teams.store');

    Route::get('teams/{team}', [TeamController::class, 'show'])
        ->middleware(EnsurePermission::class.':teams.view')
        ->name('teams.show');

    Route::patch('teams/{team}', [TeamController::class, 'update'])
        ->middleware(EnsurePermission::class.':teams.manage')
        ->name('teams.update');

    Route::delete('teams/{team}', [TeamController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':teams.manage')
        ->name('teams.destroy');
});
