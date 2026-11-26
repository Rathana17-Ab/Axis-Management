<?php

namespace App\Enums;

enum FeatureUpgradeState: string
{
    case Locked = 'locked';
    case Available = 'available';
    case Upgraded = 'upgraded';

    public function label(): string
    {
        return match ($this) {
            self::Locked => 'Restricted',
            self::Available => 'Available',
            self::Upgraded => 'Upgraded',
        };
    }
}
