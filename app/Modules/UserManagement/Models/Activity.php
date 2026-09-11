<?php

namespace App\Modules\UserManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RuntimeException;

/**
 * The single audit trail for the whole application.
 *
 * Every meaningful business action lands here, whoever performed it and
 * whatever module it came from. Two rules make it trustworthy:
 *
 * 1. Rows are append-only. Once written a row can never be edited or deleted
 *    through the model, so history cannot be quietly rewritten — not by a
 *    user, and not by a bug. See the booted() guards.
 * 2. Order is deterministic. Several activities routinely land in the same
 *    second (one transaction writing an update, a status change and an
 *    assignment), so ordering by created_at alone is ambiguous. Every reader
 *    goes through chronological()/timeline(), which breaks the tie on the
 *    auto-increment id — insertion order, which for an append-only log is the
 *    true order.
 *
 * Subjects are stored in the indexed subject_type/subject_id pair rather than
 * inside the properties JSON, so "everything that happened to this task" is an
 * index lookup instead of a table scan.
 */
class Activity extends Model
{
    public const SUBJECT_TASK = 'task';

    public const SUBJECT_PROJECT = 'project';

    public const SUBJECT_TEAM = 'team';

    protected $fillable = [
        'user_id',
        'subject_user_id',
        'action',
        'module',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'subject_id' => 'integer',
        ];
    }

    /**
     * Append-only enforcement.
     *
     * The audit log has no legitimate update or delete path, so both are
     * refused at the model rather than merely left unrouted. Purging a
     * decommissioned module's rows stays possible through the query builder in
     * a migration, which is deliberate: that is a schema change, reviewed and
     * versioned, not something a request can reach.
     */
    protected static function booted(): void
    {
        static::updating(function (self $activity) {
            throw new RuntimeException('Activity records are append-only and cannot be modified.');
        });

        static::deleting(function (self $activity) {
            throw new RuntimeException('Activity records are append-only and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Writing
    |--------------------------------------------------------------------------
    */

    /**
     * The actor is whoever the caller names, and only then whoever is logged in.
     *
     * Services are handed an explicit actor, and that is the honest answer even
     * when there is no session at all — a queued job, a console command or an
     * automation rule all act on someone's behalf without being "logged in".
     * Falling back to auth() first would have recorded those as nobody.
     */
    public static function log(string $action, array $attributes = []): self
    {
        return self::create(array_merge($attributes, [
            'action' => $action,
            'user_id' => $attributes['user_id'] ?? auth()->id(),
            'ip_address' => $attributes['ip_address'] ?? request()->ip(),
        ]));
    }

    /**
     * Logs an action against a subject, filling the indexed columns.
     *
     * The subject id is mirrored into properties under its conventional key so
     * existing readers of properties->task_id keep working.
     */
    public static function logFor(string $action, string $subjectType, int $subjectId, array $attributes = []): self
    {
        $properties = $attributes['properties'] ?? [];
        $properties[$subjectType.'_id'] ??= $subjectId;

        return self::log($action, array_merge($attributes, [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
        ]));
    }

    /**
     * Logs a field-level change set, or nothing at all when it is empty.
     *
     * This is the guard against the commonest source of audit bloat: a save
     * that changed nothing still reaching the log. A caller can hand over
     * whatever diff it computed and trust that an empty one is not recorded.
     */
    public static function logChanges(string $action, string $subjectType, int $subjectId, array $changes, array $attributes = []): ?self
    {
        if ($changes === []) {
            return null;
        }

        $attributes['properties'] = array_merge($attributes['properties'] ?? [], ['changes' => $changes]);

        return self::logFor($action, $subjectType, $subjectId, $attributes);
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    /**
     * Newest first, deterministically. Ties on created_at break on id.
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * One subject's trail — an index lookup on activities_subject_idx.
     */
    public function scopeForSubject(Builder $query, string $subjectType, int $subjectId): Builder
    {
        return $query->where('subject_type', $subjectType)->where('subject_id', $subjectId);
    }

    /**
     * Everything that happened to one project, including its tasks.
     *
     * A project timeline showing only project-level rows would omit the work
     * itself, which is most of what happens to a project.
     *
     * `$taskIds` is normally a query rather than an array, so a project with
     * thousands of tasks never materialises its ids in PHP just to build an
     * IN list — the database resolves it as a semi-join against the indexed
     * subject_id.
     *
     * @param  array<int, int>|EloquentBuilder|QueryBuilder  $taskIds
     */
    public function scopeForProjectTimeline(Builder $query, int $projectId, array|EloquentBuilder|QueryBuilder $taskIds): Builder
    {
        return $query->where(function (Builder $inner) use ($projectId, $taskIds) {
            $inner->where(fn (Builder $q) => $q->forSubject(self::SUBJECT_PROJECT, $projectId));

            if (is_array($taskIds) && $taskIds === []) {
                return;
            }

            $inner->orWhere(fn (Builder $q) => $q
                ->where('subject_type', self::SUBJECT_TASK)
                ->whereIn('subject_id', $taskIds));
        });
    }
}
