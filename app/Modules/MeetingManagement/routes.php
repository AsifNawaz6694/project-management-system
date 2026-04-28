<?php

use App\Modules\MeetingManagement\Http\Controllers\ActionItemController;
use App\Modules\MeetingManagement\Http\Controllers\MeetingController;
use App\Modules\MeetingManagement\Http\Controllers\MeetingTemplateController;
use App\Modules\UserManagement\Http\Middleware\EnsurePermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('meetings', [MeetingController::class, 'index'])
        ->middleware(EnsurePermission::class.':meetings.view')
        ->name('meetings.index');

    Route::get('meetings/create', [MeetingController::class, 'create'])
        ->middleware(EnsurePermission::class.':meetings.create')
        ->name('meetings.create');

    Route::post('meetings', [MeetingController::class, 'store'])
        ->middleware(EnsurePermission::class.':meetings.create')
        ->name('meetings.store');

    Route::get('meetings/templates', [MeetingTemplateController::class, 'index'])
        ->middleware(EnsurePermission::class.':meetings.view')
        ->name('meetings.templates.index');

    Route::post('meetings/templates', [MeetingTemplateController::class, 'store'])
        ->middleware(EnsurePermission::class.':meetings.create')
        ->name('meetings.templates.store');

    Route::patch('meetings/templates/{template}', [MeetingTemplateController::class, 'update'])
        ->middleware(EnsurePermission::class.':meetings.create')
        ->name('meetings.templates.update');

    Route::delete('meetings/templates/{template}', [MeetingTemplateController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':meetings.manage')
        ->name('meetings.templates.destroy');

    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])
        ->middleware(EnsurePermission::class.':meetings.view')
        ->name('meetings.show');

    Route::get('meetings/{meeting}/edit', [MeetingController::class, 'edit'])
        ->name('meetings.edit');

    Route::patch('meetings/{meeting}', [MeetingController::class, 'update'])
        ->name('meetings.update');

    Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy'])
        ->name('meetings.destroy');

    Route::post('meetings/{meeting}/notes', [MeetingController::class, 'saveNotes'])
        ->name('meetings.notes');

    Route::post('meetings/{meeting}/rsvp', [MeetingController::class, 'rsvp'])
        ->name('meetings.rsvp');

    Route::post('meetings/{meeting}/action-items', [ActionItemController::class, 'store'])
        ->name('meetings.action-items.store');
    Route::patch('meetings/{meeting}/action-items/{actionItem}', [ActionItemController::class, 'update'])
        ->name('meetings.action-items.update');
    Route::delete('meetings/{meeting}/action-items/{actionItem}', [ActionItemController::class, 'destroy'])
        ->name('meetings.action-items.destroy');
    Route::post('meetings/{meeting}/action-items/{actionItem}/convert', [ActionItemController::class, 'convertToTask'])
        ->name('meetings.action-items.convert');
});
