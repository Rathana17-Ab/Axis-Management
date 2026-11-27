<?php

use App\Models\Module;
use App\Models\ProjectTask;
use App\Models\User;

it('remaps old inactive task assignees and module teams to current active users', function () {
    $developer = User::factory()->create(['name' => 'Current Dev']);
    $leader = User::factory()->leader()->create(['name' => 'Current Lead']);
    $oldDeveloper = User::factory()->inactive()->create(['id' => 999, 'name' => 'Old Handler']);
    $oldLeader = User::factory()->leader()->inactive()->create(['id' => 998, 'name' => 'Old Lead']);

    $task = ProjectTask::factory()->create([
        'emp_id' => $oldDeveloper->id,
        'assigned_by_id' => $oldLeader->id,
    ]);
    $module = Module::factory()->create(['team_members' => ['Old Handler', 'Current Dev', 'Rathana']]);

    $migration = require database_path('migrations/2026_11_27_100137_remap_legacy_assignees_to_current_users.php');
    $migration->up();

    expect($task->fresh()->emp_id)->toBe($developer->id)
        ->and($task->fresh()->assigned_by_id)->toBe($leader->id)
        ->and($module->fresh()->team_members)->toBe(['Current Dev']);
});

it('clears stale assigners when no active leader exists', function () {
    $developer = User::factory()->create(['name' => 'Current Dev']);
    $oldLeader = User::factory()->leader()->inactive()->create(['id' => 998, 'name' => 'Old Lead']);
    $task = ProjectTask::factory()->create([
        'emp_id' => $developer->id,
        'assigned_by_id' => $oldLeader->id,
    ]);

    $migration = require database_path('migrations/2026_11_27_100137_remap_legacy_assignees_to_current_users.php');
    $migration->up();

    expect($task->fresh()->assigned_by_id)->toBeNull();
});
