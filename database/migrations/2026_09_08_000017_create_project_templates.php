<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32)->default('blue');
            $table->foreignId('workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('permission_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('duration_days')->nullable();
            // Milestones and starter tasks, both stored relative to day zero.
            $table->json('milestones')->nullable();
            $table->json('tasks')->nullable();
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_templates');
    }
};
