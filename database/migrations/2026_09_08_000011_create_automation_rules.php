<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('description', 255)->nullable();
            // Null scope means the rule runs on every project.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('trigger', 48);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('run_order')->default(0);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamps();

            // The engine's hot path: active rules for a trigger, in order.
            $table->index(['trigger', 'is_active'], 'automation_trigger_idx');
            $table->index('project_id');
        });

        Schema::create('automation_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('field', 48);
            $table->string('operator', 24);
            // Scalar or JSON list, depending on the operator.
            $table->text('value')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('automation_rule_id');
        });

        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('automation_rule_id');
        });

        // Every execution is recorded so a rule that quietly does the wrong
        // thing is diagnosable rather than mysterious.
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('message', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['automation_rule_id', 'created_at'], 'automation_run_rule_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_conditions');
        Schema::dropIfExists('automation_rules');
    }
};
