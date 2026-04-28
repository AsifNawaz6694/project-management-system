<?php

use App\Modules\Communication\Http\Controllers\ProjectCommentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post('projects/{project}/comments', [ProjectCommentController::class, 'store'])->name('projects.comments.store');
    Route::delete('projects/{project}/comments/{comment}', [ProjectCommentController::class, 'destroy'])->name('projects.comments.destroy');
});
