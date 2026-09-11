<?php

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Short code used to build human-readable task keys: WEB-142.
            $table->string('key', 10)->nullable()->after('slug');
            $table->unsignedInteger('task_sequence')->default(0)->after('key');
        });

        $this->backfillProjectKeys();

        Schema::table('projects', function (Blueprint $table) {
            $table->unique('key');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('id');
            $table->foreignId('team_id')->nullable()->after('assignee_id')->constrained()->nullOnDelete();
            $table->date('start_date')->nullable()->after('due_date');
            $table->timestamp('archived_at')->nullable()->after('completed_at');
        });

        $this->backfillTaskNumbers();

        Schema::table('tasks', function (Blueprint $table) {
            $table->unique(['project_id', 'number']);
            $table->index(['team_id', 'status']);
            $table->index('archived_at');
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('mentions');
        });
    }

    /**
     * Derive a unique uppercase code per project from its title.
     */
    private function backfillProjectKeys(): void
    {
        $used = [];

        DB::table('projects')->orderBy('id')->select('id', 'title')->chunkById(200, function ($rows) use (&$used) {
            foreach ($rows as $row) {
                $words = preg_split('/[^A-Za-z0-9]+/', (string) $row->title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                if (count($words) >= 2) {
                    $base = strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 2));
                } else {
                    $base = strtoupper(substr(($words[0] ?? 'PRJ'), 0, 3));
                }

                $base = preg_replace('/[^A-Z0-9]/', '', $base) ?: 'PRJ';
                $base = str_pad(substr($base, 0, 4), 2, 'X');

                $candidate = $base;
                $n = 2;
                while (in_array($candidate, $used, true)) {
                    $candidate = $base.$n++;
                }
                $used[] = $candidate;

                DB::table('projects')->where('id', $row->id)->update(['key' => $candidate]);
            }
        });
    }

    /**
     * Number existing tasks per project in creation order so keys stay stable.
     */
    private function backfillTaskNumbers(): void
    {
        $projectIds = DB::table('tasks')->distinct()->pluck('project_id');

        foreach ($projectIds as $projectId) {
            $n = 0;
            DB::table('tasks')
                ->where('project_id', $projectId)
                ->orderBy('id')
                ->select('id')
                ->chunkById(500, function ($rows) use (&$n) {
                    foreach ($rows as $row) {
                        DB::table('tasks')->where('id', $row->id)->update(['number' => ++$n]);
                    }
                });

            DB::table('projects')->where('id', $projectId)->update(['task_sequence' => $n]);
        }
    }

    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropIndex(['team_id', 'status']);
            $table->dropIndex(['archived_at']);
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn(['number', 'start_date', 'archived_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'task_sequence']);
        });
    }
};
