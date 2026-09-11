<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\NotificationCenter\Services\NotificationDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public const DIGEST_MODES = ['immediate', 'daily', 'off'];

    public function __construct(private readonly NotificationDelivery $delivery) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/notifications', [
            'preferences' => $this->delivery->matrixFor($user),
            'digest' => $user->email_digest ?? 'immediate',
            'digestModes' => self::DIGEST_MODES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'digest' => ['required', Rule::in(self::DIGEST_MODES)],
            'preferences' => ['present', 'array'],
            'preferences.*.group' => ['required', 'string', 'max:32'],
            'preferences.*.in_app' => ['boolean'],
            'preferences.*.email' => ['boolean'],
        ]);

        $user = $request->user();

        $user->forceFill(['email_digest' => $data['digest']])->save();
        $this->delivery->saveMatrix($user, $data['preferences']);

        return back()->with('status', 'Notification preferences saved.');
    }
}
