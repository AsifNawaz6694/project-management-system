<?php

namespace App\Modules\UserManagement\Mail;

use App\Models\User;
use App\Modules\UserManagement\Services\TwoFactorService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your verification code: '.$this->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.two-factor-code',
            with: [
                'user' => $this->user,
                'code' => $this->code,
                'minutes' => TwoFactorService::CODE_TTL_MINUTES,
            ],
        );
    }
}
