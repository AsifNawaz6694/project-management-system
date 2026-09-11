<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('target_task_id')->constrained('tasks')->cascadeOnDelete();
            // blocks | blocked_by | relates_to | duplicates | duplicated_by | clones | cloned_by
            $table->string('type', 24);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_task_id', 'target_task_id', 'type'], 'task_links_unique');
            $table->index(['target_task_id', 'type']);
        });

        Schema::create('task_watchers', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['task_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('query');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'name']);
            $table->index('is_shared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
        Schema::dropIfExists('task_watchers');
        Schema::dropIfExists('task_links');
    }
};
