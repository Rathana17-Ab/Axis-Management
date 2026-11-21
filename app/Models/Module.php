<?php

namespace App\Models;

use App\Enums\WorkStatus;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'code', 'name', 'description', 'proj_badge', 'badge_class', 'customer_name', 'customer_sub', 'cust_badge',
    'product_domain', 'start_date', 'deadline', 'manager_name', 'team_members', 'maint_end',
    'closed_at_label', 'status', 'overdue_label',
])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'team_members' => 'array',
            'status' => WorkStatus::class,
        ];
    }

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
