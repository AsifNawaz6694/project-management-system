<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kr_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('value', 14, 2);
            $table->string('confidence')->default('on_track');
            $table->text('note')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['key_result_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kr_updates');
    }
};
