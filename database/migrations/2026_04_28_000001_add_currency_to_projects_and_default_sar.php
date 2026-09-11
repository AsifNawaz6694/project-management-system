<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'currency')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('currency', 8)->default('SAR')->after('budget');
            });
        }

        // Guarded: the expenses table is dropped by a later migration, so this
        // is a no-op on any database migrated past that point.
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('currency', 8)->default('SAR')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'currency')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('currency', 8)->default('USD')->change();
            });
        }
    }
};
