<?php

use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('reports', ReportController::class)
        ->middleware(EnsurePermission::class.':reports.view')
        ->name('reports.index');
});
