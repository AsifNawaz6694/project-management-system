<?php

namespace App\Modules\MeetingManagement\Models;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use SoftDeletes;

    public const KINDS = ['one_on_one', 'team', 'standup', 'retro', 'planning', 'kickoff', 'custom'];

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    public const RECURRENCE = ['none', 'daily', 'weekly', 'biweekly', 'monthly'];

    protected $fillable = [
        'organizer_id',
        'project_id',
        'template_id',
        'series_id',
        'title',
        'kind',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'status',
        'recurrence_rule',
        'recurrence_until',
        'notes',
        'summary',
        'summary_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence_until' => 'datetime',
            'summary_generated_at' => 'datetime',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MeetingTemplate::class, 'template_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['role', 'rsvp_status', 'responded_at'])
            ->withTimestamps();
    }

    public function participantRows(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class)->orderBy('position')->orderBy('id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class)->latest('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasPermission('meetings.manage')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('organizer_id', $user->id)
                ->orWhereHas('participants', fn ($p) => $p->where('users.id', $user->id));
        });
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('starts_at', '<', now())->orderByDesc('starts_at');
    }
}
