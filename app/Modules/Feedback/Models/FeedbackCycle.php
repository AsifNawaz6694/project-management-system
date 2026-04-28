<?php

namespace App\Modules\Feedback\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackCycle extends Model
{
    use SoftDeletes;

    public const KINDS = ['peer', '360', 'manager', 'self', 'team'];

    public const STATUSES = ['draft', 'active', 'closed'];

    protected $fillable = [
        'created_by_id',
        'name',
        'kind',
        'description',
        'starts_at',
        'ends_at',
        'status',
        'anonymous',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'anonymous' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class)->orderBy('position');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(FeedbackRequest::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasPermission('feedback.manage')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('created_by_id', $user->id)
                ->orWhereHas('requests', fn ($r) => $r->where('reviewer_id', $user->id)->orWhere('subject_user_id', $user->id));
        });
    }
}
