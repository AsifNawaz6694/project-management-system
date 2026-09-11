<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the audit log an indexable subject.
 *
 * The timeline queries used to ask `whereJsonContains('properties->task_id')`,
 * which MySQL 5.7 cannot serve from an index — every task and project page
 * scanned the whole activities table. subject_type/subject_id carry the same
 * fact in two ordinary indexed columns.
 *
 * The index is (subject_type, subject_id, created_at, id): the leading pair
 * selects one subject's rows, and the trailing pair supplies the deterministic
 * chronological order the timeline pages on. subject_type is kept to 32 chars
 * so the composite key stays comfortably inside MySQL 5.7's limits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('subject_type', 32)->nullable()->after('module');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');

            $table->index(['subject_type', 'subject_id', 'created_at', 'id'], 'activities_subject_idx');
            // The global log orders by the same pair, so paging stays stable.
            $table->index(['created_at', 'id'], 'activities_chrono_idx');
        });

        $this->backfill('tasks', 'task', 'task_id');
        $this->backfill('projects', 'project', 'project_id');
    }

    /**
     * Recovers the subject of rows written before these columns existed.
     *
     * Only rows carrying a single scalar id are backfilled — a bulk row holds
     * `task_ids` (plural) and genuinely has no one subject, so it keeps none.
     */
    private function backfill(string $module, string $subjectType, string $key): void
    {
        DB::statement(
            'UPDATE activities
                SET subject_type = ?,
                    subject_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(properties, ?)) AS UNSIGNED)
              WHERE module = ?
                AND subject_id IS NULL
                AND properties IS NOT NULL
                AND JSON_EXTRACT(properties, ?) IS NOT NULL',
            [$subjectType, '$.'.$key, $module, '$.'.$key]
        );
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_subject_idx');
            $table->dropIndex('activities_chrono_idx');
            $table->dropColumn(['subject_type', 'subject_id']);
        });
    }
};
