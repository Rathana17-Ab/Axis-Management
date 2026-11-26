<?php

namespace App\Models;

use App\Enums\EmployeePosition;
use App\Enums\EmployeeStatus;
use App\Enums\WorkspacePermission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'job_title', 'department', 'department_id', 'position', 'position_id', 'description', 'status', 'avatar_initials', 'can_login'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Default attribute values (mirrors the column default).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'can_login' => 'boolean',
        'position' => EmployeePosition::class,
        'status' => EmployeeStatus::class,
    ];

    /**
     * Keep the directory links and the legacy task-assignment columns in sync.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $isCreating = ! $user->exists;

            if ($user->isDirty('department_id')) {
                if ($user->department_id === null) {
                    if ($isCreating && filled($user->department)) {
                        $user->department_id = Department::query()->where('name', $user->department)->value('id');
                    } else {
                        $user->department = null;
                    }
                } else {
                    $user->department = Department::query()->whereKey($user->department_id)->value('name');
                }
            } elseif ($user->isDirty('department')) {
                $user->department_id = filled($user->department)
                    ? Department::query()->where('name', $user->department)->value('id')
                    : null;
            }

            if ($user->isDirty('position_id')) {
                $position = Position::query()->find($user->position_id);

                if ($position !== null) {
                    $user->position = $position->legacyPosition();
                }
            } elseif ($user->isDirty('position')) {
                $user->position_id = $user->position === null
                    ? null
                    : Position::query()->where('code', $user->position->value)->value('id');
            }
        });
    }

    /**
     * Tasks assigned to this employee.
     *
     * @return HasMany<ProjectTask, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'emp_id');
    }

    /**
     * Roles assigned through the workspace permission system.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Teams this person belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    /**
     * The directory department linked to this member.
     *
     * @return BelongsTo<Department, $this>
     */
    public function departmentRecord(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * The directory position linked to this member.
     *
     * @return BelongsTo<Position, $this>
     */
    public function positionRecord(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Work queues shared with or assigned to this person.
     *
     * @return BelongsToMany<WorkQueue, $this>
     */
    public function workQueues(): BelongsToMany
    {
        return $this->belongsToMany(WorkQueue::class, 'user_work_queue')->withPivot('access');
    }

    /**
     * The team member operating the workspace: the signed-in user, or the legacy
     * first member with login access when the prototype opens without a login.
     */
    public static function currentWorkspaceUser(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user ?? self::query()->where('can_login', true)->orderBy('id')->first();
    }

    /**
     * Whether this user has a workspace role (users without one keep the legacy open-console behavior).
     */
    public function hasWorkspaceRole(): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->isNotEmpty();
        }

        return $this->roles()->exists();
    }

    /**
     * Whether one of this user's roles grants the given permission.
     */
    public function hasWorkspacePermission(WorkspacePermission $permission): bool
    {
        if (! $this->exists) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(function (Role $role) use ($permission): bool {
                if ($role->relationLoaded('permissions')) {
                    return $role->permissions->contains('key', $permission->value);
                }

                return $role->permissions()
                    ->where('key', $permission->value)
                    ->exists();
            });
        }

        return $this->roles()
            ->whereHas('permissions', fn (Builder $query): Builder => $query->where('key', $permission->value))
            ->exists();
    }

    /**
     * Employees who can assign tasks to others.
     */
    public function scopeLeaders(Builder $query): Builder
    {
        return $query->where('position', EmployeePosition::Leader);
    }

    /**
     * Employees who are currently active (can be given work).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EmployeeStatus::Active);
    }

    /**
     * Whether this employee is a leader (allowed to assign tasks).
     */
    public function isLeader(): bool
    {
        return $this->position === EmployeePosition::Leader;
    }

    /**
     * Whether this employee is a developer (can be assigned tasks).
     */
    public function isDeveloper(): bool
    {
        return $this->position === EmployeePosition::Developer;
    }

    /**
     * Two-letter avatar initials, falling back to the name when none are stored.
     *
     * @return Attribute<string, never>
     */
    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => $this->avatar_initials ?: Str::upper(
            Str::of($this->name)
                ->explode(' ')
                ->filter()
                ->map(fn (string $word): string => Str::substr($word, 0, 1))
                ->take(2)
                ->implode('')
        ));
    }
}
