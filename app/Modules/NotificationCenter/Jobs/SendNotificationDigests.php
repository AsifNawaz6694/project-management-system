<?php

namespace App\Modules\NotificationCenter\Jobs;

use App\Models\User;
use App\Modules\NotificationCenter\Services\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily round-up for users who chose digest delivery instead of immediate email.
 */
class SendNotificationDigests implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationDelivery $delivery): void
    {
        User::query()
            ->where('status', 'active')
            ->where('email_digest', 'daily')
            ->whereNotNull('email')
            ->chunkById(100, function ($users) use ($delivery) {
                foreach ($users as $user) {
                    if ($delivery->sendDigest($user)) {
                        $user->forceFill(['digest_sent_at' => now()])->save();
                    }
                }
            });
    }
}
