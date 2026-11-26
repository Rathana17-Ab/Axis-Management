<?php

namespace App\Models;

use App\Enums\WorkspacePermission;
use App\Enums\WorkStatus;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id', 'sort_order', 'code', 'name', 'description', 'proj_badge', 'badge_class', 'customer_name', 'customer_sub', 'cust_badge',
        'product_domain', 'start_date', 'deadline', 'manager_name', 'team_members', 'maint_end',
        'closed_at_label', 'status', 'overdue_label',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'deadline' => 'date',
        'team_members' => 'array',
        'status' => WorkStatus::class,
    ];

    /**
     * The project (e.g. "EOT Axis") this module belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Average task progress (0–100, two decimals), matching the prototype's `computeProjectCompletion()`.
     */
    public function computeCompletion(): float
    {
        $tasks = $this->tasks;

        if ($tasks->isEmpty()) {
            return 0.0;
        }

        return round($tasks->avg(fn (ProjectTask $task): float => $task->progress), 2);
    }

    /**
     * Human-friendly remaining / overdue label for a deadline, computed from the current date.
     */
    public static function overdueLabel(Carbon $deadline): string
    {
        $days = (int) today()->diffInDays($deadline->copy()->startOfDay(), false);

        return $days >= 0
            ? $days.' days remaining'
            : abs($days).' days overdue';
    }

    /**
     * Whether this module appears in the member's workspace list.
     *
     * @param  array<string, list<int>>|null  $sharedIdsByType
     */
    public static function isVisibleTo(?User $user, self $module, ?array $sharedIdsByType = null): bool
    {
        return self::query()
            ->whereKey($module->id)
            ->visibleTo($user, $sharedIdsByType)
            ->exists();
    }

    /**
     * Restrict modules to those the member can see in the workspace list.
     *
     * @param  array<string, list<int>>|null  $sharedIdsByType
     */
    public function scopeVisibleTo(Builder $query, ?User $user, ?array $sharedIdsByType = null): Builder
    {
        if ($user === null || ! $user->hasWorkspaceRole() || $user->hasWorkspacePermission(WorkspacePermission::RecordsViewAll)) {
            return $query;
        }

        $sharedIdsByType ??= RecordShare::idsByTypeFor($user);
        $moduleIds = $sharedIdsByType[(new self)->getMorphClass()] ?? [];
        $projectIds = $sharedIdsByType[(new Project)->getMorphClass()] ?? [];
        $taskIds = $sharedIdsByType[(new ProjectTask)->getMorphClass()] ?? [];

        return $query->where(function (Builder $query) use ($moduleIds, $projectIds, $taskIds, $user): void {
            $query->whereIn('modules.id', $moduleIds)
                ->orWhereIn('modules.project_id', $projectIds)
                ->orWhereHas('tasks', fn (Builder $taskQuery): Builder => $taskQuery->whereIn('project_tasks.id', $taskIds))
                ->orWhereJsonContains('modules.team_members', $user->name);
        });
    }

    /**
     * Generate the next sequential module code (e.g. PROJ-1889).
     */
    public static function nextCode(): string
    {
        $highest = static::query()->pluck('code')
            ->map(fn (string $code): int => (int) preg_replace('/\D/', '', $code))
            ->max() ?? 1887;

        return 'PROJ-'.($highest + 1);
    }
}
