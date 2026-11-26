<?php

namespace App\Http\Controllers;

use App\Enums\FeatureUpgradeState;
use App\Http\Requests\UpdateFeatureUpgradeRequest;
use App\Http\Resources\FeatureUpgradeResource;
use App\Models\FeatureUpgrade;
use Illuminate\Http\JsonResponse;

class FeatureUpgradeController extends Controller
{
    /**
     * List the P18 access and upgrade states.
     */
    public function index(): JsonResponse
    {
        return FeatureUpgradeResource::collection(FeatureUpgrade::query()->orderBy('id')->get())
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Change an upgrade state.
     */
    public function update(UpdateFeatureUpgradeRequest $request, FeatureUpgrade $featureUpgrade): FeatureUpgradeResource
    {
        $featureUpgrade->update([
            'state' => FeatureUpgradeState::from($request->validated('state'))->value,
        ]);

        return FeatureUpgradeResource::make($featureUpgrade);
    }
}
