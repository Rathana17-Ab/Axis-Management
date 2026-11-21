<?php

use App\Models\CtoReport;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskIssueNote;
use App\Models\User;
use Database\Seeders\AxisSystemSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

it('seeds the complete prototype data set', function () {
    $this->seed(AxisSystemSeeder::class);

    expect(Project::count())->toBe(1)
        ->and(Module::count())->toBe(9)
        ->and(ProjectTask::count())->toBe(14)
        ->and(TaskIssueNote::count())->toBe(10)
        ->and(Schema::hasTable('milestone_steps'))->toBeFalse()
        ->and(Schema::hasTable('business_models'))->toBeFalse()
        ->and(CtoReport::count())->toBe(5)
        ->and(User::count())->toBe(11)
        ->and(Schema::hasTable('sod_roles'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'sod_role_code'))->toBeFalse();
});

it('seeds the Axis Core System module with its tasks and live sidebar timer', function () {
    $this->seed(AxisSystemSeeder::class);

    $core = Module::with('tasks')->firstWhere('code', 'PROJ-1888');
    $sidebar = $core->tasks->firstWhere('name', 'Sidebar');

    expect($core->name)->toBe('Axis Core System')
        ->and($core->team_members)->toBe(['Chan Darong', 'Phal Kristar', 'Ti Tong'])
        ->and($core->computeCompletion())->toBe(94.33)
        ->and($sidebar->is_running)->toBeTrue()
        ->and($sidebar->act_seconds)->toBe(50710)
        ->and($sidebar->issueNotes()->count())->toBe(2);
});

it('seeds team members who can sign in with the prototype password', function () {
    $this->seed(AxisSystemSeeder::class);

    $lead = User::firstWhere('email', 'rathana@eocambo.dev');

    expect($lead->can_login)->toBeTrue()
        ->and(Hash::check(AxisSystemSeeder::DEFAULT_PASSWORD, $lead->password))->toBeTrue();
});

it('groups every module under the EOT Axis project and links tasks to employees', function () {
    $this->seed(AxisSystemSeeder::class);

    $project = Project::firstWhere('name', 'EOT Axis');

    expect($project->modules()->count())->toBe(9)
        ->and(ProjectTask::whereNull('emp_id')->count())->toBe(0)
        ->and(User::leaders()->pluck('name')->sort()->values()->all())->toBe(['Maco Buntheoun', 'Sopha Eot'])
        ->and(User::firstWhere('name', 'Chan Darong'))
        ->email->toBeNull()
        ->can_login->toBeFalse();
});
