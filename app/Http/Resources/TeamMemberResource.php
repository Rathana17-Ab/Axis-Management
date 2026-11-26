<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class TeamMemberResource extends JsonResource
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
            'name' => $this->name,
            'title' => $this->job_title,
            'department' => $this->department,
            'departmentId' => $this->department_id,
            'departmentName' => $this->departmentRecord?->name ?? $this->department,
            'position' => $this->position?->value,
            'positionId' => $this->position_id,
            'positionName' => $this->positionRecord?->name ?? ($this->position?->value === 'leader' ? 'Leader' : 'Developer'),
            'description' => $this->description,
            'status' => $this->status?->value,
            'hasAccount' => filled($this->email),
            'email' => $this->email,
            'username' => $this->email,
            'initials' => $this->initials,
            'active' => $this->can_login,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('slug')->all()),
            'teams' => $this->whenLoaded('teams', fn () => $this->teams->pluck('name')->all()),
        ];
    }
}
