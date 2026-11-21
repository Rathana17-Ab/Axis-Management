<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align projects, modules, tasks and employees with the CTO sheet:
 * description on every entity, one shared status (Not yet started / Processing / Finished),
 * module start_date, and an active/inactive employee status.
 */
return new class extends Migration
{
    /**
     * Old status label => new status label.
     *
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'Pending' => 'Not yet started',
        'In Progress' => 'Processing',
        'On Hold' => 'Processing',
        'Review' => 'Processing',
        'Completed' => 'Finished',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('description')->nullable()->after('deadline');
            $table->string('status')->default('Not yet started')->after('description');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->renameColumn('deposit_date', 'start_date');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('status')->default('Not yet started')->change();
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('status')->default('Not yet started')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('description')->nullable()->after('position');
            $table->string('status')->default('active')->after('description');
        });

        foreach (['modules', 'project_tasks'] as $table) {
            foreach (self::STATUS_MAP as $old => $new) {
                DB::table($table)->where('status', $old)->update(['status' => $new]);
            }
        }

        DB::table('projects')->whereExists(function ($query) {
            $query->from('modules')
                ->whereColumn('modules.project_id', 'projects.id')
                ->where('modules.status', 'Processing');
        })->update(['status' => 'Processing']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $reverse = ['Not yet started' => 'Pending', 'Processing' => 'In Progress', 'Finished' => 'Completed'];

        foreach (['modules', 'project_tasks'] as $table) {
            foreach ($reverse as $new => $old) {
                DB::table($table)->where('status', $new)->update(['status' => $old]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['description', 'status']);
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->enum('status', ['In Progress', 'Completed', 'Pending', 'Review'])->default('In Progress')->change();
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->enum('status', ['In Progress', 'Completed', 'Pending', 'On Hold'])->default('In Progress')->change();
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->renameColumn('start_date', 'deposit_date');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['description', 'status']);
        });
    }
};
