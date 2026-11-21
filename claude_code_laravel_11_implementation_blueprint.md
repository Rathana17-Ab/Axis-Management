# EOT CoreSystem (Axis Suite v4.5) — Laravel 11 Full-Stack Implementation Blueprint

> **Usage Instruction:** Place `axis_pm_prototype.html` in the root of your Laravel project (e.g., `prototype/axis_pm_prototype.html`) and paste Section 1 into Claude Code (or save this file as `CLAUDE.md` in your repository root).

---

## 1. Master Prompt for Claude Code (Copy & Paste)

```text
Act as a Principal Laravel 11 Full-Stack Architect. I have a complete, production-ready interactive HTML prototype located at `prototype/axis_pm_prototype.html` for "EOT CoreSystem — Axis Project Management (Axis Suite v4.5)".

Your mission is to convert this prototype into a real, database-backed Laravel 11 application while preserving 100% of its visual identity, Midnight Cerulean Navy theme (#081121 / #15294B), Forest-Sage action buttons (#3E6B48), border-only KPI card themes, 16-profile Web Audio API SoundFX synthesizer (`window.SoundFX`), button lifecycle engine (`window.runButtonLifecycle`), and semantic icon choreography (`window.triggerIconLifecycle`).

Execute the build in the following strict phases:
1. Create all 8 database migrations with proper foreign keys, indexes, and JSON/enum columns.
2. Create all Eloquent Models with relationships, casts, and helper methods (`computeCompletion()`, `formattedActTime()`).
3. Create a comprehensive `AxisSystemSeeder` that seeds the exact 9 Axis Modules, all initial Tasks, Task Issue Notes, 5 Milestone Steps, 4 Business Models, 4 CTO Reports, 8 Engineering Team Members, and all 35 Hotel PMS Segregation of Duties (SoD) Roles from the prototype.
4. Create RESTful/AJAX Controllers and routes matching the prototype's URL structure (`/admin/dashboard`, `/admin/project`, `/admin/project-task-list/{project}`, `/admin/project-milestone-steps`, `/admin/business-models`, `/admin/cto-reports`, `/admin/user-management`, `/admin/role-sod-matrix`).
5. Build the Blade layout (`resources/views/layouts/axis.blade.php`) and page views/partials so that every inline edit (task status, progress %, est hours, live stopwatch timer start/pause/sync, and the slide-over Developer Issue & Fix Drawer) persists to MySQL/PostgreSQL via `fetch()` with CSRF protection while keeping instant optimistic UI updates and acoustic sound effects.
6. Run `php artisan migrate:fresh --seed` and verify all routes and API endpoints pass feature tests.
```

---

## 2. Database Schema & Migrations (8 Core Tables)

### 2.1 `business_models`
```php
Schema::create('business_models', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('description')->default('N/A');
    $table->timestamps();
});
```

### 2.2 `milestone_steps`
```php
Schema::create('milestone_steps', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedInteger('sort_order')->default(1);
    $table->enum('status', ['Active', 'Inactive'])->default('Active');
    $table->timestamps();
});
```

### 2.3 `sod_roles` (35 Hotel PMS & Enterprise Governance Roles)
```php
Schema::create('sod_roles', function (Blueprint $table) {
    $table->id();
    $table->string('role_code')->unique(); // e.g., ROLE-PMS-ADMINISTRATOR
    $table->string('label');               // e.g., PMS Administrator
    $table->string('archetype')->index();  // administrator, manager, supervisor, finance-operator, operator, etc.
    $table->string('permitted_pages')->default('1');
    $table->boolean('can_approve')->default(false);
    $table->text('approves_summary')->nullable();
    $table->text('creates_summary')->nullable();
    $table->text('sod_rules')->nullable(); // e.g., SOD-001 No self approval | SOD-002 Rate override
    $table->timestamps();
});
```

### 2.4 `users` (Engineering Squad & Login Access Control)
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('position')->default('Full-Stack Developer');
    $table->string('sod_role_code')->default('ROLE-FRONT-DESK');
    $table->string('avatar_initials', 4)->nullable();
    $table->boolean('can_login')->default(true);
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
```

### 2.5 `projects` (The 9 Axis Product Suite Modules)
```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique(); // e.g., PROJ-1888
    $table->string('name');           // e.g., Axis Core System
    $table->string('proj_badge', 4)->default('A');
    $table->string('badge_class')->default('bg-sky-500/20 text-sky-300 border border-sky-400/30');
    $table->string('customer_name');  // e.g., Mr. CHE
    $table->string('customer_sub')->nullable(); // e.g., (Desanana Co.,Ltd)
    $table->string('cust_badge', 4)->default('C');
    $table->string('product_domain')->index(); // Axis Core Suite, Axis Hospitality PMS, Axis Commercial POS, Axis Enterprise Finance
    $table->date('deposit_date')->nullable();
    $table->date('deadline')->nullable();
    $table->string('manager_name');
    $table->json('team_members')->nullable(); // Array of engineer names
    $table->string('maint_end')->default('-');
    $table->string('closed_at_label')->default('-');
    $table->enum('status', ['In Progress', 'Completed', 'Pending', 'On Hold'])->default('In Progress');
    $table->string('overdue_label')->nullable();
    $table->timestamps();
});
```

### 2.6 `project_tasks` (Module Features & Live Chronometer Tasks)
```php
Schema::create('project_tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable(); // Supports * primary and - sub-task lines
    $table->enum('priority', ['High', 'Medium', 'Low'])->default('High');
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->string('handler_name')->index();
    $table->enum('status', ['In Progress', 'Completed', 'Pending', 'Review'])->default('In Progress');
    $table->decimal('progress', 5, 2)->default(0.00);
    $table->decimal('est_hours', 8, 2)->default(8.00);
    $table->unsignedBigInteger('act_seconds')->default(0);
    $table->boolean('is_running')->default(false);
    $table->timestamp('timer_started_at')->nullable(); // Server-accurate timestamp calculation
    $table->unsignedInteger('sort_order')->default(1);
    $table->timestamps();
});
```

### 2.7 `task_issue_notes` (Slide-Over Developer Encounter & Fix Drawer)
```php
Schema::create('task_issue_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
    $table->string('category')->default('Bug / Blocker'); // Bug / Blocker, API / DB Issue, UI / State, SoD / Edge Case
    $table->enum('severity', ['Critical', 'High', 'Medium', 'Low'])->default('High');
    $table->string('git_branch')->nullable();
    $table->enum('status', ['Open', 'Investigating', 'Resolved'])->default('Open');
    $table->text('encountered');
    $table->text('resolution')->nullable();
    $table->string('author_name');
    $table->timestamps();
});
```

### 2.8 `cto_reports` (Daily & Weekly Engineering Standups)
```php
Schema::create('cto_reports', function (Blueprint $table) {
    $table->id();
    $table->enum('report_type', ['Daily', 'Weekly'])->default('Daily');
    $table->date('report_date');
    $table->string('handler_name')->index();
    $table->string('module_name');
    $table->text('summary');
    $table->string('blocker')->default('None');
    $table->decimal('hours_logged', 5, 2)->default(8.00);
    $table->timestamps();
});
```

---

## 3. Laravel Route & Controller Architecture (`routes/web.php`)

```php
use App\Http\Controllers\AxisWorkspaceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\TaskIssueNoteController;
use App\Http\Controllers\MilestoneStepController;
use App\Http\Controllers\BusinessModelController;
use App\Http\Controllers\CtoReportController;
use App\Http\Controllers\TeamAccessController;
use App\Http\Controllers\SodRoleMatrixController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/project');

Route::prefix('admin')->name('admin.')->group(function () {
    // Primary Workspace Shell (Hydrates initial JSON state or renders specific Blade tabs)
    Route::get('/dashboard', [AxisWorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/project', [AxisWorkspaceController::class, 'index'])->name('projects.index');
    Route::get('/project-task-list/{project}', [AxisWorkspaceController::class, 'showTaskList'])->name('projects.tasks');
    Route::get('/project-milestone-steps', [AxisWorkspaceController::class, 'index'])->name('milestones.index');
    Route::get('/business-models', [AxisWorkspaceController::class, 'index'])->name('business-models.index');
    Route::get('/cto-reports', [AxisWorkspaceController::class, 'index'])->name('reports.index');
    Route::get('/user-management', [AxisWorkspaceController::class, 'index'])->name('team.index');
    Route::get('/role-sod-matrix', [AxisWorkspaceController::class, 'index'])->name('roles.index');

    // JSON API Endpoints for Real-Time Inline Editing & Persistence
    Route::prefix('api')->name('api.')->group(function () {
        // Projects
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

        // Tasks & Live Chronometer
        Route::post('/projects/{project}/tasks', [ProjectTaskController::class, 'store'])->name('tasks.store');
        Route::patch('/tasks/{task}', [ProjectTaskController::class, 'update'])->name('tasks.update');
        Route::post('/tasks/{task}/timer/toggle', [ProjectTaskController::class, 'toggleTimer'])->name('tasks.timer.toggle');
        Route::post('/tasks/{task}/timer/reset', [ProjectTaskController::class, 'resetTimer'])->name('tasks.timer.reset');
        Route::delete('/tasks/{task}', [ProjectTaskController::class, 'destroy'])->name('tasks.destroy');

        // Developer Issue & Fix Notes Drawer
        Route::post('/tasks/{task}/issues', [TaskIssueNoteController::class, 'store'])->name('issues.store');
        Route::patch('/issues/{issue}', [TaskIssueNoteController::class, 'update'])->name('issues.update');
        Route::post('/issues/{issue}/cycle-status', [TaskIssueNoteController::class, 'cycleStatus'])->name('issues.cycle');
        Route::delete('/issues/{issue}', [TaskIssueNoteController::class, 'destroy'])->name('issues.destroy');

        // Milestones, Business Models, CTO Reports & Team Access
        Route::post('/milestones', [MilestoneStepController::class, 'store']);
        Route::delete('/milestones/{milestoneStep}', [MilestoneStepController::class, 'destroy']);
        Route::post('/business-models', [BusinessModelController::class, 'store']);
        Route::delete('/business-models/{businessModel}', [BusinessModelController::class, 'destroy']);
        Route::post('/cto-reports', [CtoReportController::class, 'store']);
        Route::post('/team-members', [TeamAccessController::class, 'store']);
        Route::patch('/team-members/{user}/toggle-login', [TeamAccessController::class, 'toggleLogin']);
        Route::post('/session/switch', [TeamAccessController::class, 'switchSession']);
    });
});
```

---

## 4. Server-Accurate Stopwatch Chronometer Logic (`ProjectTaskController.php`)

Instead of relying only on browser `setInterval`, persist `timer_started_at` in the database so that refreshing the page or opening the project on another machine calculates the exact elapsed seconds:

```php
public function toggleTimer(ProjectTask $task)
{
    if ($task->is_running) {
        // Pause timer & accumulate elapsed seconds
        $elapsed = $task->timer_started_at ? now()->diffInSeconds($task->timer_started_at) : 0;
        $task->update([
            'act_seconds' => $task->act_seconds + max(0, $elapsed),
            'is_running' => false,
            'timer_started_at' => null,
        ]);
    } else {
        // Start timer & transition Pending -> In Progress
        $task->update([
            'status' => $task->status === 'Pending' ? 'In Progress' : $task->status,
            'is_running' => true,
            'timer_started_at' => now(),
        ]);
    }

    return response()->json([
        'success' => true,
        'task' => $task->fresh(),
    ]);
}
```

---

## 5. Bridging Frontend Prototype State with Laravel Database (`window.AxisAPI`)

In your Blade template (`resources/views/axis/workspace.blade.php`), hydrate the initial state directly from Eloquent and add a lightweight `AxisAPI` helper so all existing UI functions (`updateTaskStatus`, `updateTaskProgress`, `toggleTaskStopwatch`, `submitDrawerIssueNote`, etc.) persist immediately to MySQL:

```javascript
// 1. Hydrate from Laravel Controller instead of hardcoded arrays
let projects = @json($projectsJson);
let milestoneSteps = @json($milestoneStepsJson);
let businessModels = @json($businessModelsJson);
let ctoReports = @json($ctoReportsJson);
let teamMembers = @json($teamMembersJson);
const SOD_ROLES = @json($sodRolesJson);

// 2. Universal CSRF Fetch Wrapper
window.AxisAPI = {
    async request(url, method = 'POST', payload = {}) {
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: method === 'GET' ? undefined : JSON.stringify(payload)
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return await res.json();
    }
};
```

---

## 6. Verification Checklist for Claude Code

- [ ] **Visual & Theme Parity**: `#081121` obsidian background, `#15294B` floating rounded sidebar with Pin/Rail button, `#3E6B48` forest-sage primary pill buttons, and border-only colored KPI cards (`.axis-kpi-cyan`, `.axis-kpi-emerald`, `.axis-kpi-violet`, `.axis-kpi-amber`).
- [ ] **Sticky Command Bars**: Both `#sticky-filter-bar` (Projects) and `#sticky-task-filter-bar` (Task List Detail) remain docked on scroll and flash the results card on filter change.
- [ ] **Slide-Over Drawers**: Clicking any `ISSUES / DEV NOTES` badge opens `#dev-issue-drawer` and persists new notes/status cycles to `task_issue_notes`; clicking any row in the 35-Role Matrix opens `#modal-sod-drawer`.
- [ ] **Acoustic & Micro-Interaction Engines**: `window.SoundFX` (16 Web Audio API profiles), `window.runButtonLifecycle`, and `window.triggerIconLifecycle` remain intact in `public/js/axis-ui-engine.js`.