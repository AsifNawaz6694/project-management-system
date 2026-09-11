<?php

namespace App\Modules\NotificationCenter\Services;

use App\Models\User;
use App\Modules\NotificationCenter\Mail\NotificationMail;
use App\Modules\NotificationCenter\Models\Notification;
use App\Modules\NotificationCenter\Models\NotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Decides which channels a notification reaches, and sends the email one.
 *
 * Kept separate from NotificationService so the write path (creating the record)
 * stays independent of delivery, which can fail for reasons that must not roll
 * back the notification itself.
 */
class NotificationDelivery
{
    /** Groups that email by default when the user has expressed no preference. */
    private const EMAIL_BY_DEFAULT = [
        Notification::GROUP_TASKS,
        Notification::GROUP_MENTIONS,
        Notification::GROUP_DEADLINES,
        Notification::GROUP_PROJECTS,
    ];

    /** @var array<int, array<string, NotificationPreference>> */
    private array $cache = [];

    public function wantsInApp(User $user, string $group): bool
    {
        return $this->preference($user, $group)?->in_app ?? true;
    }

    public function wantsEmail(User $user, string $group): bool
    {
        if ($user->email_digest === 'off') {
            return false;
        }

        $preference = $this->preference($user, $group);

        if ($preference) {
            return $preference->email;
        }

        return in_array($group, self::EMAIL_BY_DEFAULT, true);
    }

    /**
     * Send one notification by email now, unless the user batches into a digest.
     */
    public function emailNow(User $user, Notification $notification): bool
    {
        if ($user->email_digest !== 'immediate') {
            return false;
        }

        if (! $this->wantsEmail($user, $notification->group)) {
            return false;
        }

        if ($notification->emailed_at !== null) {
            return false;
        }

        return $this->send($user, collect([$notification]), false);
    }

    /**
     * Send everything unread and un-emailed as a single digest.
     */
    public function sendDigest(User $user): bool
    {
        $pending = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->whereNull('emailed_at')
            ->latest()
            ->limit(25)
            ->get()
            ->filter(fn (Notification $n) => $this->wantsEmail($user, $n->group))
            ->values();

        if ($pending->isEmpty()) {
            return false;
        }

        return $this->send($user, $pending, true);
    }

    /**
     * @param  Collection<int, Notification>  $items
     */
    private function send(User $user, $items, bool $isDigest): bool
    {
        if (blank($user->email)) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new NotificationMail($user, $items, $isDigest));
        } catch (\Throwable $e) {
            // Delivery must never break the action that produced the notification;
            // the in-app record already exists either way.
            Log::warning('Notification email failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        Notification::query()
            ->whereIn('id', $items->pluck('id'))
            ->update(['emailed_at' => now()]);

        return true;
    }

    private function preference(User $user, string $group): ?NotificationPreference
    {
        $this->cache[$user->id] ??= NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('group')
            ->all();

        return $this->cache[$user->id][$group] ?? null;
    }

    /**
     * Every group with the effective setting for this user, for the settings UI.
     *
     * @return array<int, array{group: string, in_app: bool, email: bool}>
     */
    public function matrixFor(User $user): array
    {
        return collect(Notification::GROUPS)->map(fn (string $group) => [
            'group' => $group,
            'in_app' => $this->wantsInApp($user, $group),
            'email' => $this->preference($user, $group)?->email
                ?? in_array($group, self::EMAIL_BY_DEFAULT, true),
        ])->all();
    }

    /**
     * @param  array<int, array{group: string, in_app: bool, email: bool}>  $rows
     */
    public function saveMatrix(User $user, array $rows): void
    {
        foreach ($rows as $row) {
            if (! in_array($row['group'] ?? null, Notification::GROUPS, true)) {
                continue;
            }

            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'group' => $row['group']],
                ['in_app' => (bool) ($row['in_app'] ?? true), 'email' => (bool) ($row['email'] ?? true)],
            );
        }

        unset($this->cache[$user->id]);
    }
}
