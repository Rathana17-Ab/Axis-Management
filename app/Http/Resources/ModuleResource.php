<?php

namespace App\Http\Resources;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Module
 */
class ModuleResource extends JsonResource
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
            'sortOrder' => $this->sort_order,
            'projectId' => $this->project_id,
            'projectName' => $this->whenLoaded('project', fn () => $this->project?->name),
            'code' => $this->code,
            'customer' => $this->customer_name,
            'customerSub' => $this->customer_sub,
            'custBadge' => $this->cust_badge,
            'name' => $this->name,
            'description' => $this->description,
            'projBadge' => $this->proj_badge,
            'badgeClass' => $this->badge_class,
            'product' => $this->product_domain,
            'deadline' => $this->deadline?->toDateString(),
            'startDate' => $this->start_date?->toDateString(),
            'manager' => $this->manager_name,
            'team' => $this->team_members ?? [],
            'maintEnd' => $this->maint_end,
            'closedAt' => $this->closed_at_label,
            'status' => $this->status->value,
            'overdue' => $this->overdue_label,
            'tasks' => ProjectTaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
