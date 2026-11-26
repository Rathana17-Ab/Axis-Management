<?php

namespace App\Http\Resources;

use App\Enums\QueueAccess;
use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\WorkQueue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkQueue
 */
class WorkQueueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $user */
        $user = User::currentWorkspaceUser();
        $access = null;

        if ($user !== null) {
            $access = $user->hasWorkspacePermission(WorkspacePermission::QueuesViewAll)
                ? QueueAccess::Editor->value
                : ($this->owner_id === $user->id
                    ? QueueAccess::Editor->value
                    : $this->whenLoaded('users', fn () => $this->users->firstWhere('id', $user->id)?->pivot->access));
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'assignmentRule' => $this->assignment_rule,
            'teamId' => $this->team_id,
            'teamName' => $this->whenLoaded('team', fn () => $this->team?->name),
            'ownerId' => $this->owner_id,
            'isActive' => $this->is_active,
            'members' => $this->whenLoaded('users', fn () => $this->users->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'access' => $member->pivot->access,
            ])->all()),
            'access' => $access,
        ];
    }
}
