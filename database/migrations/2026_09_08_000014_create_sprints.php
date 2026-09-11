<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('goal', 500)->nullable();
            $table->string('state', 16)->default('future');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'state']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('sprint_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            // Half points are common enough to be worth the decimal.
            $table->decimal('story_points', 5, 1)->nullable()->after('estimate_minutes');

            $table->index('sprint_id');
        });

        // One row per sprint per day. Burndown needs to know what the board
        // looked like on a past day, and no other table records that.
        Schema::create('sprint_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('remaining_points', 8, 1)->default(0);
            $table->decimal('completed_points', 8, 1)->default(0);
            $table->unsignedInteger('remaining_tasks')->default(0);
            $table->unsignedInteger('completed_tasks')->default(0);
            $table->timestamps();

            $table->unique(['sprint_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprint_snapshots');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['sprint_id']);
            $table->dropColumn(['sprint_id', 'story_points']);
        });

        Schema::dropIfExists('sprints');
    }
};
