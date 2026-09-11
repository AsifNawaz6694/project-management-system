<?php

use App\Modules\Reporting\Http\Controllers\ExportController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('reports', ReportController::class)
        ->middleware(EnsurePermission::class.':reports.view')
        ->name('reports.index');

    Route::get('reports/flow', [ExportController::class, 'flow'])
        ->middleware(EnsurePermission::class.':reports.view')
        ->name('reports.flow');

    Route::middleware(EnsurePermission::class.':reports.export')->group(function () {
        Route::get('reports/export/tasks', [ExportController::class, 'tasks'])->name('reports.export.tasks');
        Route::get('reports/print', [ExportController::class, 'printable'])->name('reports.print');
    });
});
