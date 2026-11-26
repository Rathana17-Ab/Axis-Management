<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\SyncTeamMembersRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    /**
     * List teams with their members.
     */
    public function index(): JsonResponse
    {
        return TeamResource::collection(Team::query()->with('users')->orderBy('name')->get())
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Create a team.
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        return TeamResource::make($team->load('users'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Rename a team or update its description.
     */
    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return TeamResource::make($team->load('users'));
    }

    /**
     * Delete a team.
     */
    public function destroy(Team $team): Response
    {
        $team->delete();

        return response()->noContent();
    }

    /**
     * Set the people who belong to a team.
     */
    public function syncMembers(SyncTeamMembersRequest $request, Team $team): TeamResource
    {
        $team->users()->sync($request->validated('members'));

        return TeamResource::make($team->load('users'));
    }
}
