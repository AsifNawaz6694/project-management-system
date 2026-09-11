<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Automation rules can post comments, and those have no human author.
 *
 * The column is dropped and re-added rather than altered in place because
 * changing a constrained column needs the foreign key gone first, and doctrine
 * is not installed for a plain ->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comments', function ($table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE task_comments MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('task_comments', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('task_comments')->whereNull('user_id')->delete();

        Schema::table('task_comments', function ($table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE task_comments MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('task_comments', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
