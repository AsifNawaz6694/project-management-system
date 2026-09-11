<?php

namespace App\Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public $timestamps = false;

    protected $fillable = ['automation_rule_id', 'task_id', 'status', 'message', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
