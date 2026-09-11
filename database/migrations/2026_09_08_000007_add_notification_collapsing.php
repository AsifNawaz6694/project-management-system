<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets repeated events about the same thing collapse into one notification —
 * "3 new comments" rather than three separate rows — so the unread count stays
 * meaningful instead of turning into noise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Identifies the thing the notification is about, e.g. "task:42".
            $table->string('entity_key', 64)->nullable()->after('type');
            // How many events this row represents.
            $table->unsignedInteger('event_count')->default(1)->after('entity_key');

            // Finding the collapsible row, and clearing everything about one
            // entity when the user opens it.
            $table->index(['user_id', 'entity_key', 'read_at'], 'notifications_entity_idx');
            $table->index(['user_id', 'type', 'entity_key', 'read_at'], 'notifications_collapse_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_entity_idx');
            $table->dropIndex('notifications_collapse_idx');
            $table->dropColumn(['entity_key', 'event_count']);
        });
    }
};
