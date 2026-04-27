<?php

namespace App\Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\UserManagement\Http\Requests\LoginRequest;
use App\Modules\UserManagement\Http\Requests\TwoFactorChallengeRequest;
use App\Modules\UserManagement\Models\Activity;
use App\Modules\UserManagement\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => true,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        if (! $user->two_factor_enabled) {
            return $this->completeLogin($request, $user);
        }

        $this->twoFactor->issueCode($user, $request->ip());

        $request->session()->put('2fa.user_id', $user->id);
        $request->session()->put('2fa.remember', $request->boolean('remember'));
        $request->session()->put('2fa.intended_url', $request->session()->pull('url.intended'));

        return redirect()->route('two-factor.challenge');
    }

    public function showChallenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa.user_id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('2fa.user_id'));

        return Inertia::render('auth/two-factor-challenge', [
            'email' => $user?->email,
            'status' => $request->session()->get('status'),
            'cooldown' => TwoFactorService::RESEND_COOLDOWN_SECONDS,
        ]);
    }

    public function verifyChallenge(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa.user_id');
        $user = User::findOrFail($userId);

        $key = '2fa-attempts:'.$userId;

        if (RateLimiter::tooManyAttempts($key, 6)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (! $this->twoFactor->verify($user, $request->string('code')->toString())) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired.',
            ]);
        }

        RateLimiter::clear($key);

        return $this->completeLogin($request, $user);
    }

    public function resendChallenge(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $key = '2fa-resend:'.$userId;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->with('status', 'Please wait before requesting a new code.');
        }

        RateLimiter::hit($key, TwoFactorService::RESEND_COOLDOWN_SECONDS);

        $user = User::findOrFail($userId);
        $this->twoFactor->issueCode($user, $request->ip());

        return back()->with('status', 'A new verification code has been sent.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            Activity::log('auth.logout', [
                'user_id' => $user->id,
                'subject_user_id' => $user->id,
                'module' => 'auth',
                'description' => 'Logged out',
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        $remember = (bool) $request->session()->pull('2fa.remember', $request->boolean('remember'));
        $intended = $request->session()->pull('2fa.intended_url');
        $request->session()->forget('2fa.user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Activity::log('auth.login', [
            'user_id' => $user->id,
            'subject_user_id' => $user->id,
            'module' => 'auth',
            'description' => 'Signed in',
        ]);

        return redirect()->intended($intended ?? route('dashboard', absolute: false));
    }
}
