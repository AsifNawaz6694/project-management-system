<?php

use App\Modules\Feedback\Http\Controllers\FeedbackCycleController;
use App\Modules\Feedback\Http\Controllers\FeedbackRequestController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('feedback', [FeedbackCycleController::class, 'index'])
        ->middleware(EnsurePermission::class.':feedback.view')
        ->name('feedback.cycles.index');

    Route::get('feedback/cycles/create', [FeedbackCycleController::class, 'create'])
        ->middleware(EnsurePermission::class.':feedback.manage')
        ->name('feedback.cycles.create');

    Route::post('feedback/cycles', [FeedbackCycleController::class, 'store'])
        ->middleware(EnsurePermission::class.':feedback.manage')
        ->name('feedback.cycles.store');

    Route::get('feedback/cycles/{cycle}', [FeedbackCycleController::class, 'show'])
        ->middleware(EnsurePermission::class.':feedback.view')
        ->name('feedback.cycles.show');

    Route::post('feedback/cycles/{cycle}/activate', [FeedbackCycleController::class, 'activate'])
        ->name('feedback.cycles.activate');
    Route::post('feedback/cycles/{cycle}/close', [FeedbackCycleController::class, 'close'])
        ->name('feedback.cycles.close');
    Route::delete('feedback/cycles/{cycle}', [FeedbackCycleController::class, 'destroy'])
        ->name('feedback.cycles.destroy');

    Route::get('feedback/requests/{feedbackRequest}', [FeedbackRequestController::class, 'show'])
        ->name('feedback.requests.show');
    Route::post('feedback/requests/{feedbackRequest}', [FeedbackRequestController::class, 'submit'])
        ->name('feedback.requests.submit');
    Route::post('feedback/requests/{feedbackRequest}/decline', [FeedbackRequestController::class, 'decline'])
        ->name('feedback.requests.decline');
});
