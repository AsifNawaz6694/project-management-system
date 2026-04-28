<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->mediumText('notes')->nullable();
            $table->unsignedSmallInteger('time_allocation_minutes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['meeting_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_items');
    }
};
