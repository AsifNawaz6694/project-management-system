<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the access patterns the task module actually issues.
 * Added separately from the feature migrations so they are easy to review and tune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Overdue sweeps and due-date reminders scan on due_date + status.
            $table->index(['due_date', 'status'], 'tasks_due_status_idx');
            // Reporting: completion counts bucketed by day/week.
            $table->index('completed_at', 'tasks_completed_at_idx');
            // Root-scope listing (parent_task_id IS NULL) per project.
            $table->index(['project_id', 'parent_task_id'], 'tasks_project_parent_idx');
            // "Reported by me" filter.
            $table->index('created_by_id', 'tasks_creator_idx');
        });

        Schema::table('time_logs', function (Blueprint $table) {
            // Per-user timesheet totals over a period.
            $table->index(['user_id', 'task_id'], 'time_logs_user_task_idx');
        });

        Schema::table('activities', function (Blueprint $table) {
            // The activity feed filters by module then orders by recency.
            $table->index(['module', 'created_at'], 'activities_module_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_due_status_idx');
            $table->dropIndex('tasks_completed_at_idx');
            $table->dropIndex('tasks_project_parent_idx');
            $table->dropIndex('tasks_creator_idx');
        });

        Schema::table('time_logs', function (Blueprint $table) {
            $table->dropIndex('time_logs_user_task_idx');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_module_created_idx');
        });
    }
};
