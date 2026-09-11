<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recovers the subject of team activity written before subject_type existed.
 *
 * The team detail page read its trail with whereJsonContains('properties->team_id'),
 * the same unindexable pattern the task and project timelines used. Backfilling
 * lets it use activities_subject_idx like everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "UPDATE activities
                SET subject_type = 'team',
                    subject_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(properties, '$.team_id')) AS UNSIGNED)
              WHERE module = 'teams'
                AND subject_id IS NULL
                AND properties IS NOT NULL
                AND JSON_EXTRACT(properties, '$.team_id') IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('activities')->where('subject_type', 'team')->update([
            'subject_type' => null,
            'subject_id' => null,
        ]);
    }
};
