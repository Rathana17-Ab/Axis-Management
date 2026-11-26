<?php

namespace App\Models;

use App\Enums\WorkspacePermission;
use Database\Factories\WorkQueueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkQueue extends Model
{
    /** @use HasFactory<WorkQueueFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'assignment_rule',
        'team_id',
        'owner_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Users the queue is shared with (its assignable members).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_work_queue')->withPivot('access');
    }

    /**
     * Only queues the user owns or has been assigned/shared with.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasWorkspacePermission(WorkspacePermission::QueuesViewAll)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('owner_id', $user->id)
                ->orWhereHas('users', fn (Builder $query): Builder => $query->where('user_id', $user->id));
        });
    }
}
