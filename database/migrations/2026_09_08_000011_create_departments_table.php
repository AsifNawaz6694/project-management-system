<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Departments become a first-class record instead of a free-text column.
 *
 * The old `users.department` string is folded into the new table so nothing is
 * lost on an existing install, then dropped — a user now points at a department
 * row, which is what makes "manage departments" and department-scoped reporting
 * expressible at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('job_title')->constrained()->nullOnDelete();
        });

        $this->backfill();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }

    /**
     * Turn every distinct legacy department string into a row, then repoint the
     * users that carried it.
     */
    private function backfill(): void
    {
        $names = DB::table('users')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department');

        foreach ($names as $name) {
            $id = DB::table('departments')->insertGetId([
                'slug' => Str::slug($name) ?: Str::random(8),
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('department', $name)->update(['department_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('job_title');
        });

        DB::table('users')
            ->join('departments', 'departments.id', '=', 'users.department_id')
            ->update(['users.department' => DB::raw('departments.name')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
