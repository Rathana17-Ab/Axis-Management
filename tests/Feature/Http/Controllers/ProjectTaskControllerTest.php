<?php

use App\Enums\IssueStatus;
use App\Enums\WorkStatus;
use App\Models\Module;
use App\Models\ProjectTask;
use App\Models\TaskIssueNote;
use App\Models\User;

describe('store', function () {
    it('creates a task at the end of the module list', function () {
        $module = Module::factory()->create();
        $employee = User::factory()->create(['name' => 'Ti Tong']);
        ProjectTask::factory()->for($module)->create(['sort_order' => 4]);

        $this->postJson(route('admin.api.tasks.store', $module), [
            'name' => 'Audit Log Export',
            'description' => '*Export audit log',
            'emp_id' => $employee->id,
            'priority' => 'Medium',
            'progress' => 15,
            'est_hours' => 12,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Audit Log Export')
            ->assertJsonPath('data.status', 'Processing')
            ->assertJsonPath('data.estHours', 12);

        expect(ProjectTask::firstWhere('name', 'Audit Log Export')->sort_order)->toBe(5);
    });

    it('creates a task at 0% as not yet started unless a status is chosen', function () {
        $module = Module::factory()->create();
        $employee = User::factory()->create();

        $this->postJson(route('admin.api.tasks.store', $module), ['name' => 'Fresh', 'emp_id' => $employee->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'Not yet started');

        $this->postJson(route('admin.api.tasks.store', $module), ['name' => 'Chosen', 'emp_id' => $employee->id, 'status' => 'Finished'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'Finished')
            ->assertJsonPath('data.progress', 100);
    });

    it('marks a task created at 100% progress as completed', function () {
        $module = Module::factory()->create();
        $employee = User::factory()->create(['name' => 'Ti Tong']);

        $this->postJson(route('admin.api.tasks.store', $module), [
            'name' => 'Done already',
            'emp_id' => $employee->id,
            'progress' => 100,
        ])->assertCreated()->assertJsonPath('data.status', 'Finished');
    });

    it('logs the initial issue against the new task', function (?string $resolution, string $expectedStatus) {
        $module = Module::factory()->create();
        $employee = User::factory()->create(['name' => 'Ti Tong']);

        $this->postJson(route('admin.api.tasks.store', $module), [
            'name' => 'Login',
            'emp_id' => $employee->id,
            'initial_issue' => ['encountered' => 'Safari auto-submits the form', 'resolution' => $resolution],
        ])
            ->assertCreated()
            ->assertJsonPath('data.issues.0.status', $expectedStatus)
            ->assertJsonPath('data.issues.0.author', 'Ti Tong');
    })->with([
        'with a fix' => ['Added type="button"', 'Resolved'],
        'without a fix' => [null, 'Open'],
    ]);

    it('returns 422 when the name and assignee are missing', function () {
        $module = Module::factory()->create();
        $employee = User::factory()->create(['name' => 'Ti Tong']);

        $this->postJson(route('admin.api.tasks.store', $module), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'emp_id']);
    });
});

describe('assignment', function () {
    it('records the leader who assigned the task', function () {
        $module = Module::factory()->create();
        $developer = User::factory()->create(['name' => 'Phal Kristar']);
        $leader = User::factory()->leader()->create(['name' => 'Maco Buntheoun']);

        $this->postJson(route('admin.api.tasks.store', $module), [
            'name' => 'Sales pipeline report',
            'emp_id' => $developer->id,
            'assigned_by_id' => $leader->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.employeeId', $developer->id)
            ->assertJsonPath('data.handler', 'Phal Kristar')
            ->assertJsonPath('data.assignedById', $leader->id)
            ->assertJsonPath('data.assignedBy', 'Maco Buntheoun');

        expect(ProjectTask::firstWhere('name', 'Sales pipeline report'))
            ->module_id->toBe($module->id)
            ->assignedBy->name->toBe('Maco Buntheoun');
    });

    it('returns 422 when assigning to an inactive employee or by an inactive leader', function () {
        $module = Module::factory()->create();
        $formerDeveloper = User::factory()->inactive()->create();
        $formerLeader = User::factory()->leader()->inactive()->create();

        $this->postJson(route('admin.api.tasks.store', $module), [
            'name' => 'Sales pipeline report',
            'emp_id' => $formerDeveloper->id,
            'assigned_by_id' => $formerLeader->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'emp_id' => 'Choose an active employee.',
                'assigned_by_id' => 'Only an active leader can assign tasks.',
            ]);

        expect(ProjectTask::count())->toBe(0);
    });

    it('returns 422 when the assigner is not a leader', function () {
        $module = Module::factory()->create();
        $developer = User::factory()->create();
        $anotherDeveloper = User::factory()->create();

        $this->postJson(route('admin.api.tasks.store', $module), [
            'name' => 'Not allowed',
            'emp_id' => $developer->id,
            'assigned_by_id' => $anotherDeveloper->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_by_id' => 'Only an active leader can assign tasks.']);
    });

    it('returns 422 for an assignee who does not exist', function () {
        $module = Module::factory()->create();

        $this->postJson(route('admin.api.tasks.store', $module), ['name' => 'Ghost', 'emp_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['emp_id' => 'Choose an active employee.']);
    });

    it('reassigns a task to another leader-approved assigner', function () {
        $task = ProjectTask::factory()->create();
        $leader = User::factory()->leader()->create();

        $this->patchJson(route('admin.api.tasks.update', $task), ['assigned_by_id' => $leader->id])
            ->assertOk()
            ->assertJsonPath('data.assignedById', $leader->id);
    });
});

describe('update', function () {
    it('completes the task when progress reaches 100%', function () {
        $task = ProjectTask::factory()->create();

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 100])
            ->assertOk()
            ->assertJsonPath('data.status', 'Finished');
    });

    it('moves a pending task into progress when progress is logged', function () {
        $task = ProjectTask::factory()->pending()->create();

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 10])
            ->assertOk()
            ->assertJsonPath('data.status', 'Processing');
    });

    it('stops the timer and banks elapsed time when the task is completed', function () {
        $this->freezeTime();
        $task = ProjectTask::factory()->running()->create(['act_seconds' => 60]);
        $this->travel(30)->seconds();

        $this->patchJson(route('admin.api.tasks.update', $task), ['status' => 'Finished'])
            ->assertOk()
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.running', false)
            ->assertJsonPath('data.actSeconds', 90);
    });

    it('resets progress when a completed task goes back to pending', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Finished, 'progress' => 100]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['status' => 'Not yet started'])
            ->assertOk()
            ->assertJsonPath('data.progress', 0);
    });

    it('sets a task back to not yet started when its progress is set to 0%', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Processing, 'progress' => 40]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 'Not yet started')
            ->assertJsonPath('data.progress', 0);
    });

    it('keeps a task processing at 0% while its timer is running', function () {
        $task = ProjectTask::factory()->running()->create(['status' => WorkStatus::Processing, 'progress' => 40]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 'Processing');
    });

    it('keeps a task processing at 0% once time has been logged on it', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Processing, 'progress' => 40, 'act_seconds' => 90, 'is_running' => false]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 'Processing');
    });

    it('reopens a finished task when its progress drops below 100%', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Finished, 'progress' => 100]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 60])
            ->assertOk()
            ->assertJsonPath('data.status', 'Processing')
            ->assertJsonPath('data.progress', 60);
    });

    it('never shows a processing task as 100% done', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Finished, 'progress' => 100]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['status' => 'Processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Processing')
            ->assertJsonPath('data.progress', 99);
    });

    it('resets any progress when a task is set back to not yet started', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Processing, 'progress' => 40]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['status' => 'Not yet started'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Not yet started')
            ->assertJsonPath('data.progress', 0);
    });

    it('updates the inline-editable fields', function () {
        $task = ProjectTask::factory()->create();
        $sopha = User::factory()->create(['name' => 'Sopha Eot']);

        $this->patchJson(route('admin.api.tasks.update', $task), [
            'name' => 'Renamed',
            'emp_id' => $sopha->id,
            'priority' => 'Low',
            'est_hours' => 33,
            'deadline' => '2026-12-31',
        ])
            ->assertOk()
            ->assertJsonPath('data.handler', 'Sopha Eot')
            ->assertJsonPath('data.endDate', '2026-12-31');

        $task->refresh();
        expect($task->name)->toBe('Renamed')
            ->and($task->emp_id)->toBe($sopha->id)
            ->and($task->est_hours)->toBe(33.0)
            ->and($task->deadline->toDateString())->toBe('2026-12-31');
    });

    it('lets the chosen status win when the edit drawer sends progress and status together', function () {
        $task = ProjectTask::factory()->create();

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 40, 'status' => 'Finished'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Finished')
            ->assertJsonPath('data.progress', 100);

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 100, 'status' => 'Processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Processing')
            ->assertJsonPath('data.progress', 99);
    });

    it('returns 422 for progress above 100', function () {
        $task = ProjectTask::factory()->create();

        $this->patchJson(route('admin.api.tasks.update', $task), ['progress' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('progress');
    });
});

describe('timer', function () {
    it('moves a 0% task back to not yet started when its logged time is reset', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Processing, 'progress' => 0, 'act_seconds' => 90, 'is_running' => false]);

        $this->postJson(route('admin.api.tasks.timer.reset', $task))
            ->assertOk()
            ->assertJsonPath('data.actSeconds', 0)
            ->assertJsonPath('data.status', 'Not yet started');
    });

    it('stops the timer when a running task is set back to not yet started', function () {
        $this->freezeTime();
        $task = ProjectTask::factory()->create([
            'status' => WorkStatus::Processing,
            'progress' => 40,
            'is_running' => true,
            'timer_started_at' => now(),
        ]);

        $this->patchJson(route('admin.api.tasks.update', $task), ['status' => 'Not yet started'])
            ->assertOk()
            ->assertJsonPath('data.running', false);

        $task->refresh();
        expect($task->is_running)->toBeFalse()
            ->and($task->timer_started_at)->toBeNull();
    });

    it('keeps a task with progress processing when its logged time is reset', function () {
        $task = ProjectTask::factory()->create(['status' => WorkStatus::Processing, 'progress' => 30, 'act_seconds' => 90, 'is_running' => false]);

        $this->postJson(route('admin.api.tasks.timer.reset', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'Processing');
    });

    it('starts the timer and moves a pending task into progress', function () {
        $this->freezeTime();
        $task = ProjectTask::factory()->pending()->create();

        $this->postJson(route('admin.api.tasks.timer.toggle', $task))
            ->assertOk()
            ->assertJsonPath('data.running', true)
            ->assertJsonPath('data.status', 'Processing');

        expect($task->fresh()->timer_started_at->toDateTimeString())->toBe(now()->toDateTimeString());
    });

    it('pauses the timer and accumulates the elapsed seconds', function () {
        $this->freezeTime();
        $task = ProjectTask::factory()->running()->create(['act_seconds' => 3600]);
        $this->travel(125)->seconds();

        $this->postJson(route('admin.api.tasks.timer.toggle', $task))
            ->assertOk()
            ->assertJsonPath('data.running', false)
            ->assertJsonPath('data.actSeconds', 3725);

        expect($task->fresh())
            ->act_seconds->toBe(3725)
            ->timer_started_at->toBeNull();
    });

    it('resets the logged time and stops the timer', function () {
        $task = ProjectTask::factory()->running()->create(['act_seconds' => 500]);

        $this->postJson(route('admin.api.tasks.timer.reset', $task))
            ->assertOk()
            ->assertJsonPath('data.actSeconds', 0)
            ->assertJsonPath('data.running', false);
    });
});

describe('destroy', function () {
    it('deletes the task together with its issue notes', function () {
        $note = TaskIssueNote::factory()->create();

        $this->deleteJson(route('admin.api.tasks.destroy', $note->task))->assertNoContent();

        $this->assertModelMissing($note->task);
        $this->assertModelMissing($note);
    });
});

it('formats the live actual time as HH:MM:SS', function () {
    $this->freezeTime();
    $task = ProjectTask::factory()->running()->create(['act_seconds' => 50710]);
    $this->travel(5)->seconds();

    expect($task->fresh()->formattedActTime())->toBe('14:05:15');
});

it('cycles issue statuses in prototype order', function (IssueStatus $from, IssueStatus $to) {
    expect($from->next())->toBe($to);
})->with([
    'open → investigating' => [IssueStatus::Open, IssueStatus::Investigating],
    'investigating → resolved' => [IssueStatus::Investigating, IssueStatus::Resolved],
    'resolved → open' => [IssueStatus::Resolved, IssueStatus::Open],
]);
