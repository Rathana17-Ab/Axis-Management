<?php

namespace App\Models;

use App\Enums\FeatureUpgradeState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureUpgrade extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'label', 'description', 'state'];

    protected $casts = [
        'state' => FeatureUpgradeState::class,
    ];
}
