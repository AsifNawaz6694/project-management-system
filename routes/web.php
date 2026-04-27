<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/../app/Modules/UserManagement/routes.php';
require __DIR__.'/../app/Modules/ProjectManagement/routes.php';
require __DIR__.'/../app/Modules/TaskManagement/routes.php';
