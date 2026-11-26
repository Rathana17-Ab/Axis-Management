<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_leader')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('department')->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('position')->constrained()->nullOnDelete();
        });

        DB::table('departments')->insertOrIgnore([
            'name' => 'Engineering',
            'description' => 'Product and platform engineering',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')
            ->whereNotNull('department')
            ->where('department', '<>', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->each(function (string $name): void {
                DB::table('departments')->insertOrIgnore([
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        foreach ([
            ['name' => 'Developer', 'code' => 'developer', 'is_leader' => false],
            ['name' => 'Leader', 'code' => 'leader', 'is_leader' => true],
        ] as $position) {
            DB::table('positions')->insertOrIgnore([
                ...$position,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Keep the existing string/enum columns as the source of truth for task assignment
        // while backfilling the new directory links used by the Team page.
        DB::table('users')->whereNotNull('department')->orderBy('id')->get(['id', 'department'])
            ->each(function (object $member): void {
                $departmentId = DB::table('departments')->where('name', $member->department)->value('id');

                if ($departmentId !== null) {
                    DB::table('users')->where('id', $member->id)->update(['department_id' => $departmentId]);
                }
            });

        DB::table('users')->orderBy('id')->get(['id', 'position'])
            ->each(function (object $member): void {
                $positionId = DB::table('positions')
                    ->where('code', $member->position === 'leader' ? 'leader' : 'developer')
                    ->value('id');

                if ($positionId !== null) {
                    DB::table('users')->where('id', $member->id)->update(['position_id' => $positionId]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('position_id');
        });

        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
