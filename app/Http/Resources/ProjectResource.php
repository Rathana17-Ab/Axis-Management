<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Top-level project (e.g. "EOT Axis") as consumed by the Axis workspace JS.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'startDate' => $this->start_date?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'description' => $this->description,
            'status' => $this->status->value,
            'moduleCount' => $this->whenCounted('modules'),
        ];
    }
}
