<?php

namespace App\Models;

use App\Enums\EmployeePosition;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'code', 'description', 'is_leader', 'is_active'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_leader' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Members linked to this position.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The legacy task-assignment position represented by this directory entry.
     */
    public function legacyPosition(): EmployeePosition
    {
        return $this->is_leader ? EmployeePosition::Leader : EmployeePosition::Developer;
    }
}
