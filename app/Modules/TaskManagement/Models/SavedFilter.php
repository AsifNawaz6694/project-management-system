<?php

namespace App\Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedFilter extends Model
{
    protected $fillable = ['user_id', 'name', 'query', 'is_shared'];

    protected function casts(): array
    {
        return [
            'query' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
