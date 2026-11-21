<?php

use App\Http\Controllers\AxisWorkspaceController;
use App\Http\Controllers\CtoReportController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\TaskIssueNoteController;
use App\Http\Controllers\TeamAccessController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/project');

Route::prefix('admin')->name('admin.')->group(function () {
    // Workspace shell: each URL hydrates the full state and opens the matching page.
    Route::get('/dashboard', [AxisWorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/project', [AxisWorkspaceController::class, 'index'])->name('projects.index');
    Route::get('/modules', [AxisWorkspaceController::class, 'index'])->name('modules.index');
    Route::get('/project-task-list/{module}', [AxisWorkspaceController::class, 'showTaskList'])->name('projects.tasks');
    Route::get('/cto-reports', [AxisWorkspaceController::class, 'index'])->name('reports.index');
    Route::get('/user-management', [AxisWorkspaceController::class, 'index'])->name('team.index');

    // JSON endpoints for real-time inline editing & persistence (CSRF-protected via the web group).
    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::patch('/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');

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
        Route::post('/session/switch', [TeamAccessController::class, 'switchSession'])->name('session.switch');
    });
});
