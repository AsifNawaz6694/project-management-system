<?php

namespace App\Modules\Workflow\Models;

use App\Models\User;
use App\Modules\Teams\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person or a team follows this workflow instead of the project's full chain.
 *
 * Deliberately not a polymorphic relation: there are exactly two subject types
 * and the resolver asks for them by name, so a plain type string keeps the
 * lookup a single indexed query.
 */
class WorkflowAssignment extends Model
{
    public const TYPE_USER = 'user';

    public const TYPE_TEAM = 'team';

    public const TYPES = [self::TYPE_USER, self::TYPE_TEAM];

    protected $fillable = ['workflow_id', 'assignable_type', 'assignable_id'];

    protected function casts(): array
    {
        return [
            'assignable_id' => 'integer',
            'workflow_id' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignable_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assignable_id');
    }

    public function scopeForUsers($query)
    {
        return $query->where('assignable_type', self::TYPE_USER);
    }

    public function scopeForTeams($query)
    {
        return $query->where('assignable_type', self::TYPE_TEAM);
    }
}
