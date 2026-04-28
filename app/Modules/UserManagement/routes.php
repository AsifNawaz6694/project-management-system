<?php

use App\Modules\UserManagement\Http\Controllers\ActivityLogController;
use App\Modules\UserManagement\Http\Controllers\RoleController;
use App\Modules\UserManagement\Http\Controllers\UserController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('users', [UserController::class, 'index'])
        ->middleware(EnsurePermission::class.':users.view')
        ->name('users.index');

    Route::get('users/create', [UserController::class, 'create'])
        ->middleware(EnsurePermission::class.':users.create')
        ->name('users.create');

    Route::post('users', [UserController::class, 'store'])
        ->middleware(EnsurePermission::class.':users.create')
        ->name('users.store');

    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware(EnsurePermission::class.':users.view')
        ->name('users.show');

    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware(EnsurePermission::class.':users.update')
        ->name('users.edit');

    Route::patch('users/{user}', [UserController::class, 'update'])
        ->middleware(EnsurePermission::class.':users.update')
        ->name('users.update');

    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':users.delete')
        ->name('users.destroy');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware(EnsurePermission::class.':roles.view')
        ->name('roles.index');

    Route::post('roles', [RoleController::class, 'store'])
        ->middleware(EnsurePermission::class.':roles.create')
        ->name('roles.store');

    Route::patch('roles/{role}', [RoleController::class, 'update'])
        ->middleware(EnsurePermission::class.':roles.update')
        ->name('roles.update');

    Route::patch('roles/{role}/details', [RoleController::class, 'rename'])
        ->middleware(EnsurePermission::class.':roles.update')
        ->name('roles.rename');

    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':roles.delete')
        ->name('roles.destroy');

    Route::get('activity', [ActivityLogController::class, 'index'])
        ->middleware(EnsurePermission::class.':users.view')
        ->name('activity.index');
});
