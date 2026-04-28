<?php

namespace App\Modules\MeetingManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    public const ROLES = ['organizer', 'attendee', 'optional'];

    public const RSVP = ['pending', 'accepted', 'declined', 'tentative'];

    protected $fillable = [
        'meeting_id',
        'user_id',
        'role',
        'rsvp_status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
