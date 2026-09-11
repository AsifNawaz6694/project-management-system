<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task types become scopeable to a project.
 *
 * Existing rows keep a null project_id, which means "offered everywhere" — so
 * every type that worked before still works, and per-project types are purely
 * additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_types', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('task_types', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
