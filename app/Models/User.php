<?php

namespace App\Models;

use App\Enums\EmployeePosition;
use App\Enums\EmployeeStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'job_title', 'department', 'position', 'description', 'status', 'avatar_initials', 'can_login'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values (mirrors the column default).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_login' => 'boolean',
            'position' => EmployeePosition::class,
            'status' => EmployeeStatus::class,
        ];
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
     * Employees who can assign tasks to others.
     */
    #[Scope]
    protected function leaders(Builder $query): Builder
    {
        return $query->where('position', EmployeePosition::Leader);
    }

    /**
     * Employees who are currently active (can be given work).
     */
    #[Scope]
    protected function active(Builder $query): Builder
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
