<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user control over which notification groups reach which channel.
 *
 * A missing row means "use the default" rather than "off", so a new group added
 * later starts out delivering instead of being silently muted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Notification group: tasks, projects, mentions, deadlines, system.
            $table->string('group', 32);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'group']);
        });

        Schema::table('users', function (Blueprint $table) {
            // immediate | daily | off — how email is paced for this user.
            $table->string('email_digest', 16)->default('immediate')->after('status');
            $table->timestamp('digest_sent_at')->nullable()->after('email_digest');
        });

        Schema::table('notifications', function (Blueprint $table) {
            // Set once the notification has gone out by email, so a digest never
            // sends the same item twice.
            $table->timestamp('emailed_at')->nullable()->after('read_at');
            $table->index(['user_id', 'emailed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'emailed_at']);
            $table->dropColumn('emailed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_digest', 'digest_sent_at']);
        });

        Schema::dropIfExists('notification_preferences');
    }
};
