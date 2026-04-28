<?php

use App\Modules\ExpenseManagement\Http\Controllers\ExpenseController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('expenses', [ExpenseController::class, 'index'])
        ->middleware(EnsurePermission::class.':expenses.view')
        ->name('expenses.index');

    Route::get('expenses/approvals', [ExpenseController::class, 'approvals'])
        ->middleware(EnsurePermission::class.':expenses.approve')
        ->name('expenses.approvals');

    Route::get('expenses/create', [ExpenseController::class, 'create'])
        ->middleware(EnsurePermission::class.':expenses.create')
        ->name('expenses.create');

    Route::post('expenses', [ExpenseController::class, 'store'])
        ->middleware(EnsurePermission::class.':expenses.create')
        ->name('expenses.store');

    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])
        ->middleware(EnsurePermission::class.':expenses.view')
        ->name('expenses.show');

    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])
        ->name('expenses.edit');

    Route::patch('expenses/{expense}', [ExpenseController::class, 'update'])
        ->name('expenses.update');

    Route::patch('expenses/{expense}/decision', [ExpenseController::class, 'decide'])
        ->middleware(EnsurePermission::class.':expenses.approve')
        ->name('expenses.decide');

    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])
        ->name('expenses.destroy');

    Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'downloadReceipt'])
        ->middleware(EnsurePermission::class.':expenses.view')
        ->name('expenses.receipt');
});
