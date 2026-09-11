<?php

namespace App\Modules\TaskManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskLink extends Model
{
    public const BLOCKS = 'blocks';

    public const BLOCKED_BY = 'blocked_by';

    public const RELATES_TO = 'relates_to';

    public const DUPLICATES = 'duplicates';

    public const DUPLICATED_BY = 'duplicated_by';

    public const CLONES = 'clones';

    public const CLONED_BY = 'cloned_by';

    /**
     * Link types a user may pick. The inverse of each is created automatically,
     * so the reverse-only types are not offered directly.
     */
    public const SELECTABLE = [self::BLOCKS, self::BLOCKED_BY, self::RELATES_TO, self::DUPLICATES, self::CLONES];

    public const ALL = [
        self::BLOCKS, self::BLOCKED_BY, self::RELATES_TO,
        self::DUPLICATES, self::DUPLICATED_BY, self::CLONES, self::CLONED_BY,
    ];

    /**
     * The mirror written on the other task so both sides read naturally.
     */
    public const INVERSE = [
        self::BLOCKS => self::BLOCKED_BY,
        self::BLOCKED_BY => self::BLOCKS,
        self::RELATES_TO => self::RELATES_TO,
        self::DUPLICATES => self::DUPLICATED_BY,
        self::DUPLICATED_BY => self::DUPLICATES,
        self::CLONES => self::CLONED_BY,
        self::CLONED_BY => self::CLONES,
    ];

    public const LABELS = [
        self::BLOCKS => 'blocks',
        self::BLOCKED_BY => 'is blocked by',
        self::RELATES_TO => 'relates to',
        self::DUPLICATES => 'duplicates',
        self::DUPLICATED_BY => 'is duplicated by',
        self::CLONES => 'clones',
        self::CLONED_BY => 'is cloned by',
    ];

    protected $fillable = ['source_task_id', 'target_task_id', 'type', 'created_by_id'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'source_task_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'target_task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public static function inverseOf(string $type): string
    {
        return self::INVERSE[$type] ?? self::RELATES_TO;
    }

    /**
     * Only "blocked_by" actually gates completion.
     */
    public static function isBlocking(string $type): bool
    {
        return $type === self::BLOCKED_BY;
    }
}
