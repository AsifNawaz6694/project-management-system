<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('peer')->index();
            $table->text('description')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status')->default('draft')->index();
            $table->boolean('anonymous')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('feedback_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('body');
            $table->string('kind')->default('text');
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('feedback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['feedback_cycle_id', 'subject_user_id', 'reviewer_id'], 'fb_req_cycle_subject_reviewer_unique');
            $table->index(['reviewer_id', 'status']);
            $table->index(['subject_user_id', 'status']);
        });

        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feedback_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();

            $table->unique(['feedback_request_id', 'feedback_question_id'], 'fb_resp_request_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_responses');
        Schema::dropIfExists('feedback_requests');
        Schema::dropIfExists('feedback_questions');
        Schema::dropIfExists('feedback_cycles');
    }
};
