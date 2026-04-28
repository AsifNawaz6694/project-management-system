<?php

namespace App\Modules\Feedback\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackResponse extends Model
{
    protected $fillable = [
        'feedback_request_id',
        'feedback_question_id',
        'answer',
        'rating',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(FeedbackRequest::class, 'feedback_request_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class, 'feedback_question_id');
    }
}
