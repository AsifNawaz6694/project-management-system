<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the ExpenseManagement module.
 *
 * The table goes, and so does everything the module scattered across shared
 * tables: its permission slugs (and the role links / scheme grants pointing at
 * them), its notification group, and its activity rows. Without this an existing
 * database keeps offering "Approve / reject expenses" on the roles screen for a
 * feature that no longer has a route.
 *
 * projects.budget and projects.currency go with it — the budget bar's spend and
 * utilisation were derived entirely from approved expenses, so neither column
 * has a reader left.
 *
 * Irreversible by design: down() cannot restore data the module owned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('expenses');

        // Guarded so the migration stays safe to re-run against a database that
        // has already had one or both columns removed by hand.
        $columns = array_values(array_filter(
            ['budget', 'currency'],
            fn (string $column) => Schema::hasColumn('projects', $column),
        ));

        if ($columns !== []) {
            Schema::table('projects', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        // permission_role cascades on permission_id, so deleting the permission
        // rows unassigns them from every role in one step.
        DB::table('permissions')->where('slug', 'like', 'expenses.%')->delete();
        DB::table('permission_scheme_grants')->where('permission', 'like', 'expenses.%')->delete();

        DB::table('notification_preferences')->where('group', 'expenses')->delete();
        DB::table('notifications')->where('group', 'expenses')->delete();
        DB::table('activities')->where('module', 'expenses')->delete();
    }

    public function down(): void
    {
        // Recreates the columns only. Expense records, permissions and history
        // are gone for good.
        if (! Schema::hasColumn('projects', 'budget')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->decimal('budget', 14, 2)->nullable()->after('end_date');
            });
        }

        if (! Schema::hasColumn('projects', 'currency')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('currency', 8)->default('SAR')->after('budget');
            });
        }
    }
};
