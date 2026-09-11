<?php

namespace App\Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationAction extends Model
{
    public const SET_STATUS = 'set_status';

    public const SET_PRIORITY = 'set_priority';

    public const ASSIGN = 'assign';

    public const ADD_LABEL = 'add_label';

    public const ADD_COMMENT = 'add_comment';

    public const ADD_WATCHER = 'add_watcher';

    public const SET_DUE_DATE = 'set_due_date';

    public const NOTIFY = 'notify';

    public const TYPES = [
        self::SET_STATUS,
        self::SET_PRIORITY,
        self::ASSIGN,
        self::ADD_LABEL,
        self::ADD_COMMENT,
        self::ADD_WATCHER,
        self::SET_DUE_DATE,
        self::NOTIFY,
    ];

    /** Symbolic targets accepted by assign / add_watcher / notify. */
    public const TARGET_REPORTER = 'reporter';

    public const TARGET_ASSIGNEE = 'assignee';

    public const TARGET_PROJECT_OWNER = 'project_owner';

    public const TARGET_ACTOR = 'actor';

    public const TARGET_WATCHERS = 'watchers';

    public const TARGET_NONE = 'none';

    protected $fillable = ['automation_rule_id', 'type', 'config', 'position'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }
}
