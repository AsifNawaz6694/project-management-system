<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('email');
            $table->string('phone', 32)->nullable()->after('avatar');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('department')->nullable()->after('job_title');
            $table->enum('status', ['active', 'invited', 'suspended'])->default('active')->after('department');
            $table->boolean('two_factor_enabled')->default(true)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_enabled');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar',
                'phone',
                'job_title',
                'department',
                'status',
                'two_factor_enabled',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
