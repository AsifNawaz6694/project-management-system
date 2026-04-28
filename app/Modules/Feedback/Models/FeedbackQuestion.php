<?php

namespace App\Modules\Feedback\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackQuestion extends Model
{
    public const KINDS = ['text', 'rating', 'yes_no'];

    protected $fillable = [
        'feedback_cycle_id',
        'body',
        'kind',
        'required',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(FeedbackCycle::class, 'feedback_cycle_id');
    }
}
