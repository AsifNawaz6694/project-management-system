<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('meeting_templates')->nullOnDelete();
            $table->foreignId('series_id')->nullable()->index();
            $table->string('title');
            $table->string('kind')->default('team')->index();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->string('recurrence_rule')->nullable();
            $table->dateTime('recurrence_until')->nullable();
            $table->mediumText('notes')->nullable();
            $table->mediumText('summary')->nullable();
            $table->dateTime('summary_generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_id', 'starts_at']);
            $table->index(['kind', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
