<?php

namespace App\Modules\NotificationCenter\Mail;

use App\Models\User;
use App\Modules\NotificationCenter\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * One notification, or a batch of them as a digest.
 *
 * Queued, so a slow SMTP server never blocks the request that triggered it.
 */
class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Notification>  $notifications
     */
    public function __construct(
        public User $recipient,
        public Collection $notifications,
        public bool $isDigest = false,
    ) {}

    public function envelope(): Envelope
    {
        $app = config('app.name');

        if ($this->isDigest) {
            $count = $this->notifications->count();

            return new Envelope(subject: "{$app}: {$count} update".($count === 1 ? '' : 's').' waiting');
        }

        return new Envelope(subject: $app.': '.$this->notifications->first()?->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notification',
            with: [
                'recipient' => $this->recipient,
                'items' => $this->notifications,
                'isDigest' => $this->isDigest,
                'appUrl' => rtrim(config('app.url'), '/'),
            ],
        );
    }
}
