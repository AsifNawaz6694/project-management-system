<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            // Null means the template is offered on every project.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('medium');
            $table->foreignId('task_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->decimal('story_points', 5, 1)->nullable();
            // Days from creation; a template cannot carry an absolute date.
            $table->unsignedSmallInteger('due_in_days')->nullable();
            $table->json('labels')->nullable();
            $table->json('subtasks')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('project_id');
        });

        Schema::create('task_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 16);
            // Every N days/weeks/months.
            $table->unsignedSmallInteger('interval')->default(1);
            // 0-6 for weekly, 1-31 for monthly; ignored otherwise.
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_run_on');
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The scheduler's only query: what is due today and still running?
            $table->index(['is_active', 'next_run_on'], 'recurrence_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_recurrences');
        Schema::dropIfExists('task_templates');
    }
};
