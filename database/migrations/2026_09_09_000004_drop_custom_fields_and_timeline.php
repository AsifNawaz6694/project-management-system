<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes custom fields, and the timeline view alongside them.
 *
 * The timeline owned no schema — it was a read-only view over tasks.start_date
 * and tasks.due_date, both of which stay: they are ordinary task fields the
 * form, the exports and the sort options all still use.
 *
 * Custom fields did own schema, and it goes here. Values are dropped before
 * definitions because custom_field_values.custom_field_id is constrained.
 *
 * Neither feature had permission slugs of its own — custom fields were gated by
 * tasks.view and tasks.manage-labels — so no grants need unwinding.
 *
 * Irreversible by design: down() cannot restore the values the tables held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }

    public function down(): void
    {
        // Recreated by the original migration if rolled back past it; the
        // values themselves are gone for good.
    }
};
