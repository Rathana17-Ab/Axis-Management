<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs deployments whose database predates the workspace access and team directory
 * migrations. It only creates what is missing and never drops or rewrites existing data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('label');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table): void {
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();

                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table): void {
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();

                $table->primary(['role_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('team_user')) {
            Schema::create('team_user', function (Blueprint $table): void {
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();

                $table->primary(['team_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('work_queues')) {
            Schema::create('work_queues', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('assignment_rule')->nullable();
                $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_work_queue')) {
            Schema::create('user_work_queue', function (Blueprint $table): void {
                $table->foreignId('work_queue_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('access', ['viewer', 'editor'])->default('viewer');

                $table->primary(['work_queue_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('record_shares')) {
            Schema::create('record_shares', function (Blueprint $table): void {
                $table->id();
                $table->morphs('shareable');
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('ability', ['view', 'update'])->default('view');
                $table->timestamps();

                $table->unique(['shareable_type', 'shareable_id', 'user_id'], 'record_shares_unique');
            });
        }

        if (! Schema::hasTable('feature_upgrades')) {
            Schema::create('feature_upgrades', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->string('description')->nullable();
                $table->enum('state', ['locked', 'available', 'upgraded'])->default('locked');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_leader')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('department_id')->nullable()->after('department')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'position_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('position_id')->nullable()->after('position')->constrained()->nullOnDelete();
            });
        }

        $this->seedDirectoryDefaults();
    }

    /**
     * Reverse the migrations.
     *
     * This is a non-destructive repair migration: tables that existed before it are kept intact.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }

    /**
     * Restore the default directory values and legacy-column links without overwriting user edits.
     */
    private function seedDirectoryDefaults(): void
    {
        DB::table('departments')->insertOrIgnore([
            'name' => 'Engineering',
            'description' => 'Product and platform engineering',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('users', 'department')) {
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
        }

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

        if (Schema::hasColumn('users', 'department')) {
            DB::table('users')->whereNotNull('department')->orderBy('id')->get(['id', 'department'])
                ->each(function (object $member): void {
                    $departmentId = DB::table('departments')->where('name', $member->department)->value('id');

                    if ($departmentId !== null) {
                        DB::table('users')->where('id', $member->id)->update(['department_id' => $departmentId]);
                    }
                });
        }

        if (Schema::hasColumn('users', 'position')) {
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
    }
};
