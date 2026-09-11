<?php

namespace App\Modules\NotificationCenter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'group', 'in_app', 'email'];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
