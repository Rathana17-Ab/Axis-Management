<?php

namespace App\Http\Resources;

use App\Models\TaskIssueNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskIssueNote
 */
class TaskIssueNoteResource extends JsonResource
{
    /**
     * Transform the resource into the shape consumed by the Axis workspace JS.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'taskId' => $this->project_task_id,
            'type' => $this->category,
            'severity' => $this->severity->value,
            'branch' => $this->git_branch,
            'status' => $this->status->value,
            'encountered' => $this->encountered,
            'resolution' => $this->resolution ?? '',
            'author' => $this->author_name,
            'timestamp' => $this->created_at?->format('M d, Y H:i'),
        ];
    }
}
