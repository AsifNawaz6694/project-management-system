<?php

namespace App\Modules\UserManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'subject_user_id',
        'action',
        'module',
        'description',
        'properties',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public static function log(string $action, array $attributes = []): self
    {
        return self::create(array_merge([
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ], $attributes, ['action' => $action]));
    }
}
