<?php

namespace App\Http\Resources;

use App\Models\RecordShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecordShare
 */
class RecordShareResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->whenLoaded('shareable');

        return [
            'id' => $this->id,
            'type' => class_basename($this->shareable_type),
            'shareableId' => $this->shareable_id,
            'title' => $record?->name ?? $record?->title ?? class_basename($this->shareable_type).' #'.$this->shareable_id,
            'userId' => $this->user_id,
            'userName' => $this->whenLoaded('user', fn () => $this->user?->name),
            'ability' => $this->ability,
        ];
    }
}
