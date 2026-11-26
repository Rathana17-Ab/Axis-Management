<?php

namespace App\Http\Controllers;

use App\Enums\QueueAccess;
use App\Http\Requests\StoreWorkQueueRequest;
use App\Http\Requests\SyncWorkQueueMembersRequest;
use App\Http\Requests\UpdateWorkQueueRequest;
use App\Http\Resources\WorkQueueResource;
use App\Models\User;
use App\Models\WorkQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WorkQueueController extends Controller
{
    /**
     * List queues visible to the current user. Users without a workspace role keep the legacy full view.
     */
    public function index(): JsonResponse
    {
        $user = User::currentWorkspaceUser();
        $query = WorkQueue::query()->with(['team', 'owner', 'users']);

        if ($user !== null && $user->hasWorkspaceRole()) {
            $query->visibleTo($user);
        }

        return WorkQueueResource::collection($query->orderBy('name')->get())
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Create a queue owned by the signed-in administrator.
     */
    public function store(StoreWorkQueueRequest $request): JsonResponse
    {
        $queue = WorkQueue::create([
            ...$request->validated(),
            'owner_id' => User::currentWorkspaceUser()?->id,
        ]);

        return WorkQueueResource::make($queue->load(['team', 'owner', 'users']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the queue configuration.
     */
    public function update(UpdateWorkQueueRequest $request, WorkQueue $workQueue): WorkQueueResource
    {
        $workQueue->update($request->validated());

        return WorkQueueResource::make($workQueue->load(['team', 'owner', 'users']));
    }

    /**
     * Delete a queue.
     */
    public function destroy(WorkQueue $workQueue): Response
    {
        $workQueue->delete();

        return response()->noContent();
    }

    /**
     * Share the queue with people and set their access level.
     */
    public function syncMembers(SyncWorkQueueMembersRequest $request, WorkQueue $workQueue): WorkQueueResource
    {
        $access = QueueAccess::from($request->validated('access'))->value;
        $workQueue->users()->syncWithPivotValues($request->validated('members'), ['access' => $access]);

        return WorkQueueResource::make($workQueue->load(['team', 'owner', 'users']));
    }
}
