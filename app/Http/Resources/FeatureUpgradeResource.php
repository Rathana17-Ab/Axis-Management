<?php

namespace App\Http\Resources;

use App\Models\FeatureUpgrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeatureUpgrade
 */
class FeatureUpgradeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'state' => $this->state?->value,
            'stateLabel' => $this->state?->label(),
        ];
    }
}
