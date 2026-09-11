<?php

namespace App\Modules\UserManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "who" line inside a scheme: permission X is granted to Y.
 */
class PermissionSchemeGrant extends Model
{
    /** Anyone who already holds the permission on their workspace role. */
    public const TYPE_EVERYONE = 'everyone';

    /** The user who owns the project. */
    public const TYPE_PROJECT_OWNER = 'project_owner';

    /** Any user listed on the project, whatever their project role. */
    public const TYPE_ANY_MEMBER = 'any_member';

    /** A specific project role: owner / lead / member / viewer. */
    public const TYPE_PROJECT_ROLE = 'project_role';

    /** The assignee of the task being acted on. */
    public const TYPE_ASSIGNEE = 'assignee';

    /** The user who raised the task being acted on. */
    public const TYPE_REPORTER = 'reporter';

    /** Every member of a given team. */
    public const TYPE_TEAM = 'team';

    /** One named user. */
    public const TYPE_USER = 'user';

    /** Everyone holding a given workspace role (admin / manager / employee). */
    public const TYPE_WORKSPACE_ROLE = 'workspace_role';

    public const TYPES = [
        self::TYPE_EVERYONE,
        self::TYPE_PROJECT_OWNER,
        self::TYPE_ANY_MEMBER,
        self::TYPE_PROJECT_ROLE,
        self::TYPE_ASSIGNEE,
        self::TYPE_REPORTER,
        self::TYPE_TEAM,
        self::TYPE_USER,
        self::TYPE_WORKSPACE_ROLE,
    ];

    /** Types that carry an argument in grant_value. */
    public const TYPES_WITH_VALUE = [
        self::TYPE_PROJECT_ROLE,
        self::TYPE_TEAM,
        self::TYPE_USER,
        self::TYPE_WORKSPACE_ROLE,
    ];

    protected $fillable = ['permission_scheme_id', 'permission', 'grant_type', 'grant_value'];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(PermissionScheme::class, 'permission_scheme_id');
    }
}
