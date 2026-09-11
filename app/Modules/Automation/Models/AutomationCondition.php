<?php

namespace App\Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationCondition extends Model
{
    /** Fields readable from the task itself. */
    public const FIELD_STATUS = 'status';

    public const FIELD_PRIORITY = 'priority';

    public const FIELD_ASSIGNEE = 'assignee_id';

    public const FIELD_TEAM = 'team_id';

    public const FIELD_PROJECT = 'project_id';

    public const FIELD_TASK_TYPE = 'task_type_id';

    public const FIELD_TITLE = 'title';

    public const FIELD_LABEL = 'label';

    public const FIELD_OVERDUE = 'is_overdue';

    /** Fields that only exist in the event payload. */
    public const FIELD_FROM_STATUS = 'from_status';

    public const FIELD_TO_STATUS = 'to_status';

    public const FIELD_CHANGED_FIELD = 'changed_field';

    public const FIELD_COMMENT = 'comment_body';

    public const FIELD_ACTOR = 'actor_id';

    public const FIELDS = [
        self::FIELD_STATUS,
        self::FIELD_PRIORITY,
        self::FIELD_ASSIGNEE,
        self::FIELD_TEAM,
        self::FIELD_PROJECT,
        self::FIELD_TASK_TYPE,
        self::FIELD_TITLE,
        self::FIELD_LABEL,
        self::FIELD_OVERDUE,
        self::FIELD_FROM_STATUS,
        self::FIELD_TO_STATUS,
        self::FIELD_CHANGED_FIELD,
        self::FIELD_COMMENT,
        self::FIELD_ACTOR,
    ];

    public const OPERATORS = [
        'equals',
        'not_equals',
        'in',
        'not_in',
        'contains',
        'not_contains',
        'is_empty',
        'is_not_empty',
    ];

    /** Operators that need no right-hand side. */
    public const UNARY_OPERATORS = ['is_empty', 'is_not_empty'];

    protected $fillable = ['automation_rule_id', 'field', 'operator', 'value', 'position'];
}
