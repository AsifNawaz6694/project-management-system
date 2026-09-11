<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who follows which chain of task stages.
 *
 * A project's workflow stays the canonical pipeline. This table narrows it for
 * a person or a team: the stages named by their assigned workflow are the only
 * ones they are offered when moving a task. An empty table — the state this
 * ships in — means nobody is narrowed and everyone sees the project's full
 * chain, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();

            // 'user' or 'team'. Two small subject types rather than a morph:
            // the resolver only ever asks these two questions.
            $table->string('assignable_type', 16);
            $table->unsignedBigInteger('assignable_id');

            $table->timestamps();

            // One chain per person and one per team — a subject with two
            // chains would have no defined answer.
            $table->unique(['assignable_type', 'assignable_id'], 'workflow_assignments_subject_unique');
            $table->index(['workflow_id', 'assignable_type'], 'workflow_assignments_workflow_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_assignments');
    }
};
