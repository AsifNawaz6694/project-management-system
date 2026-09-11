<?php

use App\Modules\Search\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/quick', [SearchController::class, 'quick'])->name('search.quick');
});
