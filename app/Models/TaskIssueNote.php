<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use Database\Factories\TaskIssueNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_task_id', 'category', 'severity', 'git_branch', 'status', 'encountered', 'resolution', 'author_name',
])]
class TaskIssueNote extends Model
{
    /** @use HasFactory<TaskIssueNoteFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
        ];
    }

    /**
     * @return BelongsTo<ProjectTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }
}
