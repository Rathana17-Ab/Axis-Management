<?php

namespace App\Models;

use Database\Factories\RecordShareFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecordShare extends Model
{
    /** @use HasFactory<RecordShareFactory> */
    use HasFactory;

    protected $fillable = ['shareable_type', 'shareable_id', 'user_id', 'ability'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shared record ids grouped by morph class for this member.
     *
     * @return array<string, list<int>>
     */
    public static function idsByTypeFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return static::query()
            ->where('user_id', $user->id)
            ->get(['shareable_type', 'shareable_id'])
            ->groupBy('shareable_type')
            ->map(fn ($shares): array => $shares->pluck('shareable_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all())
            ->all();
    }
}
