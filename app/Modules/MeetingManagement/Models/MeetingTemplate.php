<?php

namespace App\Modules\MeetingManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingTemplate extends Model
{
    use SoftDeletes;

    public const KINDS = ['one_on_one', 'team', 'standup', 'retro', 'planning', 'kickoff', 'custom'];

    protected $fillable = [
        'created_by_id',
        'name',
        'kind',
        'description',
        'agenda_items',
        'is_shared',
    ];

    protected function casts(): array
    {
        return [
            'agenda_items' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
