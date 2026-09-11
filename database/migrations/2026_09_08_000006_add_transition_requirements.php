<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a workflow transition demand an explanation before it is allowed —
 * e.g. QA must record why a build passed or failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_transitions', function (Blueprint $table) {
            $table->boolean('requires_comment')->default(false)->after('name');
            // Prompt shown to the user, e.g. "Why did QA fail?".
            $table->string('comment_label')->nullable()->after('requires_comment');
        });

        Schema::table('task_status_history', function (Blueprint $table) {
            // The reason captured at transition time.
            $table->text('note')->nullable()->after('to_status');
        });
    }

    public function down(): void
    {
        Schema::table('task_status_history', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        Schema::table('workflow_transitions', function (Blueprint $table) {
            $table->dropColumn(['requires_comment', 'comment_label']);
        });
    }
};
