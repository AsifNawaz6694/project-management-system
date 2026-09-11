<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            // An internal note is visible only to people who can edit the task.
            $table->boolean('is_internal')->default(false)->after('body');
            $table->index('is_internal');
        });

        // Every edit keeps the text it replaced, so "edited" is auditable
        // rather than a word next to a timestamp.
        Schema::create('comment_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_comment_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('edited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_comment_id', 'created_at'], 'revision_comment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_revisions');

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['is_internal']);
            $table->dropColumn('is_internal');
        });
    }
};
