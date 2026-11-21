<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\WorkStatus;
use Carbon\CarbonInterface;
use Database\Factories\ProjectTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTask extends Model
{
    /** @use HasFactory<ProjectTaskFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'module_id', 'emp_id', 'assigned_by_id', 'name', 'description', 'priority', 'start_date', 'deadline',
        'status', 'progress', 'est_hours', 'act_seconds', 'is_running', 'timer_started_at', 'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => TaskPriority::class,
        'status' => WorkStatus::class,
        'start_date' => 'date',
        'deadline' => 'date',
        'progress' => 'float',
        'est_hours' => 'float',
        'act_seconds' => 'integer',
        'is_running' => 'boolean',
        'timer_started_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * The employee the task is assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emp_id');
    }

    /**
     * The leader who assigned the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /**
     * @return HasMany<TaskIssueNote, $this>
     */
    public function issueNotes(): HasMany
    {
        return $this->hasMany(TaskIssueNote::class)->latest()->latest('id');
    }

    /**
     * Seconds elapsed on the running chronometer since it was last started.
     */
    public function runningElapsedSeconds(?CarbonInterface $at = null): int
    {
        if (! $this->is_running || $this->timer_started_at === null) {
            return 0;
        }

        return max(0, (int) $this->timer_started_at->diffInSeconds($at ?? now(), false));
    }

    /**
     * Accumulated seconds including the currently running segment.
     */
    public function liveActSeconds(?CarbonInterface $at = null): int
    {
        return $this->act_seconds + $this->runningElapsedSeconds($at);
    }

    /**
     * Live actual time formatted as HH:MM:SS.
     */
    public function formattedActTime(): string
    {
        $seconds = $this->liveActSeconds();

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Start the chronometer, moving a pending task into progress.
     */
    public function startTimer(): void
    {
        if ($this->is_running) {
            return;
        }

        if ($this->status === WorkStatus::NotStarted) {
            $this->status = WorkStatus::Processing;
        }

        $this->is_running = true;
        $this->timer_started_at = now();
    }

    /**
     * Pause the chronometer and bank the elapsed seconds.
     */
    public function pauseTimer(): void
    {
        $this->act_seconds = $this->liveActSeconds();
        $this->is_running = false;
        $this->timer_started_at = null;
    }

    /**
     * Stop the chronometer and clear all logged time.
     */
    public function resetTimer(): void
    {
        $this->act_seconds = 0;
        $this->is_running = false;
        $this->timer_started_at = null;

        // With no time logged and nothing done, the work hasn't really started
        if ($this->status === WorkStatus::Processing && $this->progress <= 0) {
            $this->status = WorkStatus::NotStarted;
        }
    }

    /**
     * Whether work has begun: the timer is running or has logged time.
     */
    public function hasStartedWork(): bool
    {
        return $this->is_running || $this->liveActSeconds() > 0;
    }

    /**
     * Apply a status change, keeping progress consistent with it:
     * Finished = 100% (timer stops), Not yet started = 0%, Processing is never shown as 100%.
     */
    public function applyStatus(WorkStatus $status): void
    {
        $this->status = $status;

        if ($status === WorkStatus::Finished) {
            $this->progress = 100;
            $this->pauseTimer();
        } elseif ($status === WorkStatus::NotStarted) {
            $this->progress = 0;
            $this->pauseTimer();
        } elseif ($this->progress >= 100) {
            $this->progress = 99;
        }
    }

    /**
     * Apply a progress change (clamped to 0–100), keeping the status consistent with it:
     * 100% = Finished; 0% = Not yet started (unless time is logged or running); anything between = Processing.
     */
    public function applyProgress(float $progress): void
    {
        $this->progress = min(100, max(0, $progress));

        if ($this->progress >= 100) {
            $this->status = WorkStatus::Finished;
        } elseif ($this->progress <= 0 && ! $this->hasStartedWork()) {
            $this->status = WorkStatus::NotStarted;
        } else {
            $this->status = WorkStatus::Processing;
        }
    }
}
