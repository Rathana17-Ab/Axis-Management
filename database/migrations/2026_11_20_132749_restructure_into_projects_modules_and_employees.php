<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Restructures AXIS into the CTO's model: Project → Module → Task, with tasks
 * assigned to employees (users) by a leader.
 *
 *  - The former `projects` table (Axis Core System, Axis Sales, …) becomes `modules`.
 *  - A new top-level `projects` table is created; existing modules are grouped under "EOT Axis".
 *  - Tasks move from free-text `handler_name` to `emp_id` (users) and gain `assigned_by_id`.
 *  - Users gain `department` and `position` (developer | leader); the old free-text
 *    position (job title) is kept as `job_title`.
 */
return new class extends Migration
{
    /**
     * Leaders named in the CTO's sheet that exist in the current data.
     *
     * @var list<string>
     */
    private const LEADERS = ['Maco Buntheoun', 'Sopha Eot'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Former projects become modules; create the new top-level projects table.
        Schema::rename('projects', 'modules');

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        if (DB::table('modules')->exists()) {
            $projectId = DB::table('projects')->insertGetId([
                'name' => 'EOT Axis',
                'start_date' => DB::table('modules')->min('deposit_date'),
                'deadline' => DB::table('modules')->max('deadline'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('modules')->update(['project_id' => $projectId]);
        }

        // 2. Employees: department + developer/leader position; keep the job title.
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('position', 'job_title');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->default(null)->change();
            $table->string('email')->nullable()->change();
            $table->string('department')->nullable()->after('job_title');
            $table->enum('position', ['developer', 'leader'])->default('developer')->after('department');
        });

        DB::table('users')->whereIn('name', self::LEADERS)->update(['position' => 'leader']);

        // 3. Tasks belong to modules, are assigned to an employee by a leader, and have a deadline.
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->renameColumn('project_id', 'module_id');
            $table->renameColumn('end_date', 'deadline');
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreignId('emp_id')->nullable()->after('module_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->after('emp_id')->constrained('users')->nullOnDelete();
        });

        $this->linkTaskHandlersToEmployees();

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['handler_name']);
            $table->dropColumn('handler_name');
        });
    }

    /**
     * Point every task at a user record for its former handler name, creating an
     * employee (no sign-in account) for names that have no user yet.
     */
    private function linkTaskHandlersToEmployees(): void
    {
        $names = DB::table('project_tasks')->whereNotNull('handler_name')->distinct()->pluck('handler_name');

        foreach ($names as $name) {
            $userId = DB::table('users')->where('name', $name)->value('id')
                ?? DB::table('users')->insertGetId([
                    'name' => $name,
                    'email' => null,
                    'password' => Hash::make(Str::password(32)),
                    'position' => in_array($name, self::LEADERS, true) ? 'leader' : 'developer',
                    'can_login' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('project_tasks')->where('handler_name', $name)->update(['emp_id' => $userId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('handler_name')->default('')->index();
        });

        foreach (DB::table('users')->pluck('name', 'id') as $id => $name) {
            DB::table('project_tasks')->where('emp_id', $id)->update(['handler_name' => $name]);
        }

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by_id');
            $table->dropConstrainedForeignId('emp_id');
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->renameColumn('deadline', 'end_date');
            $table->renameColumn('module_id', 'project_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['position', 'department']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('job_title', 'position');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::drop('projects');
        Schema::rename('modules', 'projects');
    }
};
