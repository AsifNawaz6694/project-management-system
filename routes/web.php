<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, '__invoke'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/../app/Modules/UserManagement/routes.php';
require __DIR__.'/../app/Modules/ProjectManagement/routes.php';
require __DIR__.'/../app/Modules/TaskManagement/routes.php';
require __DIR__.'/../app/Modules/Communication/routes.php';
require __DIR__.'/../app/Modules/ExpenseManagement/routes.php';
require __DIR__.'/../app/Modules/NotificationCenter/routes.php';
require __DIR__.'/../app/Modules/Reporting/routes.php';
require __DIR__.'/../app/Modules/Teams/routes.php';
require __DIR__.'/../app/Modules/MeetingManagement/routes.php';
require __DIR__.'/../app/Modules/Okrs/routes.php';
require __DIR__.'/../app/Modules/Feedback/routes.php';
