<?php

use App\Modules\NotificationCenter\Jobs\SendNotificationDigests;
use App\Modules\TaskManagement\Jobs\SendDueDateReminders;
use App\Modules\TaskManagement\Jobs\SendOverdueAlerts;
use App\Modules\TaskManagement\Services\SprintService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Requires a cron entry running `php artisan schedule:run` every minute.
| In local development `composer dev` already runs `queue:listen`, so queued
| jobs dispatched here are processed immediately.
|
*/

// Morning reminder for work due tomorrow.
Schedule::job(new SendDueDateReminders(1))
    ->dailyAt('08:00')
    ->name('tasks:due-tomorrow')
    ->withoutOverlapping();

// Same-day nudge.
Schedule::job(new SendDueDateReminders(0))
    ->dailyAt('08:05')
    ->name('tasks:due-today')
    ->withoutOverlapping();

// Overdue sweep, deduplicated per day inside the job.
Schedule::job(new SendOverdueAlerts)
    ->dailyAt('09:00')
    ->name('tasks:overdue')
    ->withoutOverlapping();

// One burndown reading per running sprint, taken at the end of the day.
Schedule::call(fn () => app(SprintService::class)->snapshotAllActive())
    ->dailyAt('23:50')
    ->name('sprints:snapshot')
    ->withoutOverlapping();

Artisan::command('sprints:snapshot', function () {
    $count = app(SprintService::class)->snapshotAllActive();
    $this->info("Snapshotted {$count} running sprint(s).");
})->purpose('Record the burndown reading for every running sprint');

// Daily round-up for users who chose digest delivery.
Schedule::job(new SendNotificationDigests)
    ->dailyAt('07:30')
    ->name('notifications:digest')
    ->withoutOverlapping();

Artisan::command('notifications:digest', function () {
    $this->info('Sending notification digests...');
    SendNotificationDigests::dispatchSync();
    $this->info('Done.');
})->purpose('Send notification digests now (manual trigger)');

Artisan::command('tasks:remind {--days=1}', function () {
    $this->info('Dispatching due-date reminders...');
    SendDueDateReminders::dispatchSync((int) $this->option('days'));
    $this->info('Done.');
})->purpose('Send due-date reminders now (manual trigger)');

Artisan::command('tasks:overdue', function () {
    $this->info('Dispatching overdue alerts...');
    SendOverdueAlerts::dispatchSync();
    $this->info('Done.');
})->purpose('Send overdue alerts now (manual trigger)');
