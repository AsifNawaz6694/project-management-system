<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('metric_type')->default('number');
            $table->decimal('start_value', 14, 2)->default(0);
            $table->decimal('target_value', 14, 2);
            $table->decimal('current_value', 14, 2)->default(0);
            $table->string('unit')->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->string('status')->default('on_track');
            $table->timestamps();

            $table->index(['objective_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_results');
    }
};
