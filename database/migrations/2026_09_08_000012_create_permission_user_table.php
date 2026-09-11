<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direct, per-user permission grants.
 *
 * The organisation has exactly two meaningful top-level roles; the management
 * responsibilities on top of "Employee" (backend/UI lead, product manager, …)
 * are grants against the individual rather than a role invented per job title.
 * A user's effective permission set is the union of their roles' permissions
 * and these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['permission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
    }
};
