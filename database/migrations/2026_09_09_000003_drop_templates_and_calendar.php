<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes task templates, recurring work and project templates.
 *
 * The calendar went at the same time, but it was a read-only view over
 * tasks.due_date and owned no schema, so there is nothing here for it.
 *
 * Recurrences go with the templates rather than surviving them: a schedule
 * exists only to materialise a template, so without one it has nothing to make.
 *
 * Templates never had permission slugs of their own — they were gated by
 * tasks.* and projects.* — so no grants need unwinding. What does need clearing
 * is the activity trail, which still points at a feature that has no route.
 *
 * Irreversible by design: down() cannot restore data the tables held.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Recurrences first: task_recurrences.task_template_id is constrained.
        Schema::dropIfExists('task_recurrences');
        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('project_templates');

        DB::table('activities')->where('action', 'projects.created-from-template')->delete();
    }

    public function down(): void
    {
        // The tables are recreated by their original migrations if this is ever
        // rolled back past them; the rows they held are gone for good.
    }
};
