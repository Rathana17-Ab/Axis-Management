<?php

namespace App\Http\Resources;

use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectTask
 */
class ProjectTaskResource extends JsonResource
{
    /**
     * Transform the resource into the shape consumed by the Axis workspace JS.
     *
     * `actSeconds` is server-accurate: it includes the running segment since `timer_started_at`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'moduleId' => $this->module_id,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'priority' => $this->priority->value,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->deadline?->toDateString(),
            'employeeId' => $this->emp_id,
            'handler' => $this->employee?->name ?? '',
            'assignedById' => $this->assigned_by_id,
            'assignedBy' => $this->assignedBy?->name,
            'status' => $this->status->value,
            'progress' => $this->progress,
            'estHours' => $this->est_hours,
            'actSeconds' => $this->liveActSeconds(),
            'running' => $this->is_running,
            'issues' => TaskIssueNoteResource::collection($this->whenLoaded('issueNotes')),
        ];
    }
}
