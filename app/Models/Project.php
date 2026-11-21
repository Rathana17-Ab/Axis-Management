<?php

namespace App\Models;

use App\Enums\WorkStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Top-level project (e.g. "EOT Axis") that groups product modules.
 */
#[Fillable(['name', 'start_date', 'deadline', 'description', 'status'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Default attribute values (mirrors the column default).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'Not yet started',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'status' => WorkStatus::class,
        ];
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('id');
    }
}
