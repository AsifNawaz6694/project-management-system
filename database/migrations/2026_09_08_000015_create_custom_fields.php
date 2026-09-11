<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            // Null means the field is offered on every project.
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('type', 24);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('show_on_board')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // A key is unique within its scope; two projects may both have "Severity".
            $table->unique(['project_id', 'key']);
            $table->index('project_id');
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // Everything is stored as text and cast on read — one column beats
            // eight nullable typed columns that are almost always empty.
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'task_id'], 'field_task_unique');
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
