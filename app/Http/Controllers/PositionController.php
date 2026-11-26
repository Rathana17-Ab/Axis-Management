<?php

namespace App\Http\Controllers;

use App\Enums\EmployeePosition;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PositionController extends Controller
{
    /**
     * List positions with the number of members linked to each one.
     */
    public function index(): JsonResponse
    {
        return PositionResource::collection(
            Position::query()->withCount('users')->orderBy('id')->get()
        )->response()->setStatusCode(200);
    }

    /**
     * Create a position.
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());

        return PositionResource::make($position->loadCount('users'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a position and keep linked members' task-assignment role in sync.
     */
    public function update(UpdatePositionRequest $request, Position $position): PositionResource
    {
        $position->update($request->validated());

        if ($request->has('is_leader')) {
            User::query()->where('position_id', $position->id)->update([
                'position' => $position->is_leader ? EmployeePosition::Leader : EmployeePosition::Developer,
            ]);
        }

        return PositionResource::make($position->loadCount('users'));
    }

    /**
     * Delete a position that has no members linked to it.
     */
    public function destroy(Position $position): Response|JsonResponse
    {
        if ($position->users()->exists()) {
            return response()->json([
                'message' => 'Move or unlink this position\'s team members before deleting it.',
            ], 409);
        }

        $position->delete();

        return response()->noContent();
    }
}
