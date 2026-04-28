<?php

namespace App\Modules\Okrs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrUpdate extends Model
{
    public const CONFIDENCE = ['on_track', 'at_risk', 'off_track', 'achieved'];

    protected $table = 'kr_updates';

    protected $fillable = [
        'key_result_id',
        'recorded_by_id',
        'value',
        'confidence',
        'note',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(KeyResult::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
