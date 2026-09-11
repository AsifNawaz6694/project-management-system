<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 60);
            $table->string('icon', 40)->default('circle-dot');
            $table->string('color', 24)->default('blue');
            // Sub-task types are excluded from the "create a task" picker.
            $table->boolean('is_subtask_type')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 60)->unique();
            $table->string('color', 24)->default('slate');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('label_task', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            $table->primary(['label_id', 'task_id']);
            // Reverse lookup for "show me this task's labels".
            $table->index(['task_id', 'label_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_type_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'task_type_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('task_type_id');
            });
        }

        Schema::dropIfExists('label_task');
        Schema::dropIfExists('labels');
        Schema::dropIfExists('task_types');
    }
};
