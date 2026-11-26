<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Enums\WorkStatus;
use App\Models\CtoReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Seeds the Axis Suite v4.5 workspace with the exact data set from the HTML prototype
 * (stored verbatim in `database/seeders/data/axis-prototype.json`).
 */
class AxisSystemSeeder extends Seeder
{
    /**
     * Default password for seeded team members (matches the prototype's sign-in form).
     */
    public const DEFAULT_PASSWORD = 'password123';

    /**
     * Name of the top-level project that groups the Axis modules (CTO model: Project → Module → Task).
     */
    public const PROJECT_NAME = 'EOT Axis';

    /**
     * Team leaders named in the CTO's requirements sheet (allowed to assign tasks).
     *
     * @var list<string>
     */
    public const LEADERS = ['Maco Buntheoun', 'Sopha Eot'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array{projects: list<array<string, mixed>>, ctoReports: list<array<string, mixed>>, teamMembers: list<array<string, mixed>>} $data */
        $data = File::json(database_path('seeders/data/axis-prototype.json'));

        DB::transaction(function () use ($data): void {
            $this->seedTeamMembers($data['teamMembers']);
            $this->seedModules($data['projects']);
            $this->seedCtoReports($data['ctoReports']);
            $this->call(SalesAccessSeeder::class);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $members
     */
    private function seedTeamMembers(array $members): void
    {
        foreach ($members as $member) {
            User::create([
                'name' => $member['name'],
                'email' => $member['email'],
                'job_title' => $member['position'],
                'position' => in_array($member['name'], self::LEADERS, true) ? EmployeePosition::Leader : EmployeePosition::Developer,
                'can_login' => $member['active'],
                'password' => self::DEFAULT_PASSWORD,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $projects
     */
    private function seedModules(array $modules): void
    {
        $project = Project::create([
            'name' => self::PROJECT_NAME,
            'start_date' => collect($modules)->pluck('startDate')->filter()->min(),
            'deadline' => collect($modules)->pluck('deadline')->filter()->max(),
            'status' => WorkStatus::Processing,
        ]);

        foreach ($modules as $position => $projectData) {
            $module = $project->modules()->create([
                'sort_order' => $position + 1,
                'code' => $projectData['code'],
                'name' => $projectData['name'],
                'proj_badge' => $projectData['projBadge'],
                'badge_class' => $projectData['badgeClass'],
                'customer_name' => $projectData['customer'],
                'customer_sub' => $projectData['customerSub'],
                'cust_badge' => $projectData['custBadge'],
                'product_domain' => $projectData['product'],
                'start_date' => $projectData['startDate'],
                'deadline' => $projectData['deadline'],
                'manager_name' => $projectData['manager'],
                'team_members' => $projectData['team'],
                'maint_end' => $projectData['maintEnd'],
                'closed_at_label' => $projectData['closedAt'],
                'status' => $projectData['status'],
                'overdue_label' => $projectData['overdue'],
            ]);

            foreach ($projectData['tasks'] as $position => $taskData) {
                $task = $module->tasks()->create([
                    'name' => $taskData['name'],
                    'description' => $taskData['description'],
                    'priority' => $taskData['priority'],
                    'start_date' => $taskData['startDate'],
                    'deadline' => $taskData['endDate'],
                    'emp_id' => $this->employeeId($taskData['handler']),
                    'status' => $taskData['status'],
                    'progress' => $taskData['progress'],
                    'est_hours' => $taskData['estHours'],
                    'act_seconds' => $taskData['actSeconds'],
                    'is_running' => $taskData['running'],
                    'timer_started_at' => $taskData['running'] ? now() : null,
                    'sort_order' => $position + 1,
                ]);

                // Oldest first so the newest note gets the highest id and appears on top.
                foreach (array_reverse($taskData['issues'] ?? []) as $issue) {
                    $createdAt = Carbon::createFromFormat('M d, Y H:i', $issue['timestamp']);

                    $note = $task->issueNotes()->make([
                        'category' => $issue['type'],
                        'severity' => $issue['severity'] ?? 'High',
                        'git_branch' => $issue['branch'] ?? null,
                        'status' => $issue['status'],
                        'encountered' => $issue['encountered'],
                        'resolution' => $issue['resolution'],
                        'author_name' => $issue['author'],
                    ]);
                    $note->created_at = $createdAt;
                    $note->updated_at = $createdAt;
                    $note->save();
                }
            }
        }
    }

    /**
     * The user id for an assignee name; assignees without a team account become employees
     * who cannot sign in (no email), mirroring the restructure migration.
     */
    private function employeeId(string $name): int
    {
        return User::query()->firstOrCreate(['name' => $name], [
            'email' => null,
            'password' => Str::password(32),
            'position' => in_array($name, self::LEADERS, true) ? EmployeePosition::Leader : EmployeePosition::Developer,
            'can_login' => false,
        ])->id;
    }

    /**
     * @param  list<array<string, mixed>>  $reports
     */
    private function seedCtoReports(array $reports): void
    {
        foreach ($reports as $report) {
            CtoReport::create([
                'report_type' => $report['type'],
                'recipient' => $report['recipient'] ?? 'Dear Bong and team',
                'report_date' => $report['date'],
                'date_from' => $report['date_from'] ?? null,
                'date_to' => $report['date_to'] ?? null,
                'handler_name' => $report['handler'],
                'module_name' => $report['module'],
                'summary' => $report['summary'],
                'content_json' => $report['content_json'] ?? null,
                'blocker' => $report['blocker'],
                'hours_logged' => $report['hours'],
            ]);
        }
    }
}
