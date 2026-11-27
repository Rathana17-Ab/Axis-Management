<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\AxisWorkspaceController;
use App\Http\Controllers\CtoReportController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FeatureUpgradeController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfilePasswordController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\RecordShareController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskIssueNoteController;
use App\Http\Controllers\TeamAccessController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\WorkQueueController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:login'])->name('login.store');
Route::get('/register', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')->name('register');
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware(['guest', 'throttle:register'])->name('register.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::redirect('/', '/admin/dashboard');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // Workspace shell: each URL hydrates the full state and opens the matching page.
    Route::get('/dashboard', [AxisWorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AxisWorkspaceController::class, 'index'])->name('profile');
    Route::post('/profile/photo', [ProfilePhotoController::class, 'store'])->name('profile.photo.store');
    Route::delete('/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
    Route::patch('/profile/password', [ProfilePasswordController::class, 'update'])->name('profile.password.update');
    Route::get('/project', [AxisWorkspaceController::class, 'index'])->name('projects.index');
    Route::get('/modules', [AxisWorkspaceController::class, 'index'])->name('modules.index');
    Route::get('/project-task-list/{module}', [AxisWorkspaceController::class, 'showTaskList'])->name('projects.tasks');
    Route::get('/cto-reports', [AxisWorkspaceController::class, 'index'])->name('reports.index');

    // Team remains open as in the original workspace; only the new administration pages are permission-gated.
    Route::get('/user-management', [AxisWorkspaceController::class, 'index'])->name('team.index');
    Route::get('/roles-teams', [AxisWorkspaceController::class, 'index'])
        ->middleware(['workspace.session', 'workspace.can:administration.access'])->name('roles.index');
    Route::get('/work-queues', [AxisWorkspaceController::class, 'index'])
        ->middleware(['workspace.session', 'workspace.can:queues.view_all,queues.view_shared'])->name('work-queues.index');
    Route::get('/record-sharing', [AxisWorkspaceController::class, 'index'])
        ->middleware(['workspace.session', 'workspace.can:records.view_all,records.view_shared'])->name('sharing.index');
    Route::get('/p18', [AxisWorkspaceController::class, 'index'])
        ->middleware(['workspace.session', 'workspace.can:p18.view'])->name('p18.index');

    // JSON endpoints for real-time inline editing & persistence (CSRF-protected via the web group).
    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::patch('/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
        Route::post('/modules/reorder', [ModuleController::class, 'reorder'])->name('modules.reorder');

        Route::post('/modules/{module}/tasks', [ProjectTaskController::class, 'store'])->name('tasks.store');
        Route::patch('/tasks/{task}', [ProjectTaskController::class, 'update'])->name('tasks.update');
        Route::post('/tasks/{task}/timer/toggle', [ProjectTaskController::class, 'toggleTimer'])->name('tasks.timer.toggle');
        Route::post('/tasks/{task}/timer/reset', [ProjectTaskController::class, 'resetTimer'])->name('tasks.timer.reset');
        Route::delete('/tasks/{task}', [ProjectTaskController::class, 'destroy'])->name('tasks.destroy');

        Route::post('/tasks/{task}/issues', [TaskIssueNoteController::class, 'store'])->name('issues.store');
        Route::patch('/issues/{issue}', [TaskIssueNoteController::class, 'update'])->name('issues.update');
        Route::post('/issues/{issue}/cycle-status', [TaskIssueNoteController::class, 'cycleStatus'])->name('issues.cycle');
        Route::delete('/issues/{issue}', [TaskIssueNoteController::class, 'destroy'])->name('issues.destroy');

        Route::post('/cto-reports', [CtoReportController::class, 'store'])->name('reports.store');
        Route::get('/cto-reports/{report}', [CtoReportController::class, 'show'])->name('reports.show');
        Route::patch('/cto-reports/{report}', [CtoReportController::class, 'update'])->name('reports.update');
        Route::delete('/cto-reports/{report}', [CtoReportController::class, 'destroy'])->name('reports.destroy');

        Route::post('/team-members', [TeamAccessController::class, 'store'])->name('team.store');
        Route::patch('/team-members/{user}', [TeamAccessController::class, 'update'])->name('team.update');
        Route::patch('/team-members/{user}/toggle-login', [TeamAccessController::class, 'toggleLogin'])->name('team.toggle-login');
        Route::patch('/team-members/{user}/password', [TeamAccessController::class, 'resetPassword'])->name('team.password');
        Route::delete('/team-members/{user}', [TeamAccessController::class, 'destroy'])->name('team.destroy');

        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::get('/positions', [PositionController::class, 'index'])->name('positions.index');
        Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
        Route::patch('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');
        Route::patch('/team-members/{user}/roles', [UserRoleController::class, 'update'])
            ->middleware(['workspace.session', 'workspace.can:users.manage'])->name('user-roles.update');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware(['workspace.session', 'workspace.can:roles.manage'])->name('roles.index');

        Route::get('/teams', [TeamController::class, 'index'])
            ->middleware(['workspace.session', 'workspace.can:teams.manage'])->name('teams.index');
        Route::post('/teams', [TeamController::class, 'store'])
            ->middleware(['workspace.session', 'workspace.can:teams.manage'])->name('teams.store');
        Route::patch('/teams/{team}', [TeamController::class, 'update'])
            ->middleware(['workspace.session', 'workspace.can:teams.manage'])->name('teams.update');
        Route::patch('/teams/{team}/members', [TeamController::class, 'syncMembers'])
            ->middleware(['workspace.session', 'workspace.can:teams.manage'])->name('teams.members');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])
            ->middleware(['workspace.session', 'workspace.can:teams.manage'])->name('teams.destroy');

        Route::get('/work-queues', [WorkQueueController::class, 'index'])
            ->middleware(['workspace.session', 'workspace.can:queues.view_all,queues.view_shared'])->name('work-queues.index');
        Route::post('/work-queues', [WorkQueueController::class, 'store'])
            ->middleware(['workspace.session', 'workspace.can:work_queues.manage'])->name('work-queues.store');
        Route::patch('/work-queues/{workQueue}', [WorkQueueController::class, 'update'])
            ->middleware(['workspace.session', 'workspace.can:work_queues.manage'])->name('work-queues.update');
        Route::patch('/work-queues/{workQueue}/members', [WorkQueueController::class, 'syncMembers'])
            ->middleware(['workspace.session', 'workspace.can:work_queues.manage'])->name('work-queues.members');
        Route::delete('/work-queues/{workQueue}', [WorkQueueController::class, 'destroy'])
            ->middleware(['workspace.session', 'workspace.can:work_queues.manage'])->name('work-queues.destroy');

        Route::get('/record-shares', [RecordShareController::class, 'index'])
            ->middleware(['workspace.session', 'workspace.can:records.view_all,records.view_shared'])->name('record-shares.index');
        Route::post('/record-shares', [RecordShareController::class, 'store'])
            ->middleware(['workspace.session', 'workspace.can:records.manage_all'])->name('record-shares.store');
        Route::delete('/record-shares/{recordShare}', [RecordShareController::class, 'destroy'])
            ->middleware(['workspace.session', 'workspace.can:records.manage_all'])->name('record-shares.destroy');

        Route::get('/feature-upgrades', [FeatureUpgradeController::class, 'index'])
            ->middleware(['workspace.session', 'workspace.can:p18.view'])->name('feature-upgrades.index');
        Route::patch('/feature-upgrades/{featureUpgrade}', [FeatureUpgradeController::class, 'update'])
            ->middleware(['workspace.session', 'workspace.can:p18.manage'])->name('feature-upgrades.update');
    });
});
