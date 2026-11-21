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
            'position' => $this->position?->value,
            'description' => $this->description,
            'status' => $this->status?->value,
            'hasAccount' => filled($this->email),
            'email' => $this->email,
            'initials' => $this->initials,
            'active' => $this->can_login,
        ];
    }
}
