<?php

namespace App\Http\Controllers;

use App\Enums\RecordShareAbility;
use App\Enums\WorkspacePermission;
use App\Http\Requests\StoreRecordShareRequest;
use App\Http\Resources\RecordShareResource;
use App\Models\CtoReport;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\RecordShare;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RecordShareController extends Controller
{
    /**
     * Aliases used by the UI so raw model class names never reach the browser.
     *
     * @var array<string, class-string>
     */
    private const SHAREABLE_TYPES = [
        'project' => Project::class,
        'module' => Module::class,
        'task' => ProjectTask::class,
        'report' => CtoReport::class,
    ];

    /**
     * Return records visible to the current user and the shares that grant that access.
     */
    public function index(): JsonResponse
    {
        $user = User::currentWorkspaceUser();
        $seesAll = $user === null
            || ! $user->hasWorkspaceRole()
            || $user->hasWorkspacePermission(WorkspacePermission::RecordsViewAll);

        $shares = RecordShare::query()->with(['user', 'shareable']);
        if (! $seesAll && $user !== null) {
            $shares->where('user_id', $user->id);
        }

        $canManage = $user === null
            || ! $user->hasWorkspaceRole()
            || $user->hasWorkspacePermission(WorkspacePermission::RecordsManageAll);

        return response()->json([
            'data' => RecordShareResource::collection($shares->orderBy('id')->get())->resolve(),
            'records' => $canManage ? $this->recordOptions() : [],
            'users' => $canManage ? User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
            ])->all() : [],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Share a record with a user.
     */
    public function store(StoreRecordShareRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $modelClass = self::SHAREABLE_TYPES[$validated['shareable_type']];
        $record = $modelClass::query()->findOrFail($validated['shareable_id']);

        $exists = RecordShare::query()
            ->where('shareable_type', $record->getMorphClass())
            ->where('shareable_id', $record->id)
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This record is already shared with that person.'], 422);
        }

        $share = RecordShare::create([
            'shareable_type' => $record->getMorphClass(),
            'shareable_id' => $record->id,
            'user_id' => $validated['user_id'],
            'ability' => RecordShareAbility::from($validated['ability'])->value,
        ]);

        return RecordShareResource::make($share->load(['user', 'shareable']))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove a record share.
     */
    public function destroy(RecordShare $recordShare): Response
    {
        $recordShare->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, list<array{id: int, title: string}>>
     */
    private function recordOptions(): array
    {
        return [
            'project' => Project::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Project $record): array => ['id' => $record->id, 'title' => $record->name])->all(),
            'module' => Module::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Module $record): array => ['id' => $record->id, 'title' => $record->name])->all(),
            'task' => ProjectTask::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (ProjectTask $record): array => ['id' => $record->id, 'title' => $record->name])->all(),
            'report' => CtoReport::query()->orderBy('id')->get(['id', 'summary'])
                ->map(fn (CtoReport $record): array => ['id' => $record->id, 'title' => 'Report #'.$record->id])->all(),
        ];
    }
}
