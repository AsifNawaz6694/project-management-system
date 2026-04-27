<?php

namespace App\Modules\UserManagement\Services;

use App\Models\User;
use App\Modules\UserManagement\Mail\TwoFactorCodeMail;
use App\Modules\UserManagement\Models\TwoFactorCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TwoFactorService
{
    public const CODE_TTL_MINUTES = 10;

    public const RESEND_COOLDOWN_SECONDS = 30;

    public function issueCode(User $user, ?string $ipAddress = null): TwoFactorCode
    {
        $user->twoFactorCodes()->whereNull('used_at')->delete();

        $code = $this->generateCode();

        $record = TwoFactorCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'ip_address' => $ipAddress,
        ]);

        Mail::to($user->email)->send(new TwoFactorCodeMail($user, $code));

        return $record;
    }

    public function verify(User $user, string $code): bool
    {
        $record = $user->twoFactorCodes()
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            return false;
        }

        $record->update(['used_at' => now()]);
        $user->twoFactorCodes()
            ->where('id', '!=', $record->id)
            ->whereNull('used_at')
            ->delete();

        return true;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function generateChallengeToken(): string
    {
        return Str::uuid()->toString();
    }
}
