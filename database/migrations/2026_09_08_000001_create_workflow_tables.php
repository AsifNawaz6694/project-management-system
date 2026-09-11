<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            // `key` is what lands in tasks.status — denormalised on purpose so the
            // hot path (board grouping, filters, counts) never needs a join.
            $table->string('key', 40);
            $table->string('name', 60);
            // Buckets a status for reporting: todo | in_progress | done.
            $table->string('category', 20)->default('todo');
            $table->string('color', 24)->default('slate');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->timestamps();

            $table->unique(['workflow_id', 'key']);
            $table->index(['workflow_id', 'position']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            // Null `from` means "from any status" — keeps the common case cheap.
            $table->foreignId('from_status_id')->nullable()->constrained('workflow_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('workflow_statuses')->cascadeOnDelete();
            $table->string('name', 60)->nullable();
            $table->string('required_permission')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'from_status_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('workflow_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });

        Schema::create('task_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            // Time spent in the previous status, so cycle/lead time is a sum, not a window function.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'created_at']);
            $table->index(['to_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_history');

        if (Schema::hasColumn('projects', 'workflow_id')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropConstrainedForeignId('workflow_id');
            });
        }

        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_statuses');
        Schema::dropIfExists('workflows');
    }
};
