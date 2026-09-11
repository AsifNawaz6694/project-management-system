<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('is_default');
        });

        Schema::create('permission_scheme_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_scheme_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 64);
            $table->string('grant_type', 32);
            // Null for grant types that need no argument (everyone, project_owner, assignee...).
            $table->string('grant_value', 64)->nullable();
            $table->timestamps();

            $table->index(['permission_scheme_id', 'permission'], 'scheme_permission_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('permission_scheme_id')
                ->nullable()
                ->after('workflow_id')
                ->constrained('permission_schemes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['permission_scheme_id']);
            $table->dropColumn('permission_scheme_id');
        });

        Schema::dropIfExists('permission_scheme_grants');
        Schema::dropIfExists('permission_schemes');
    }
};
