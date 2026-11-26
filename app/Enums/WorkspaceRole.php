<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case SalesAdmin = 'sales_admin';
    case SalesPerson = 'sales_person';

    /**
     * Human-readable role label shown in administration screens.
     */
    public function label(): string
    {
        return match ($this) {
            self::SalesAdmin => 'Sales Admin',
            self::SalesPerson => 'Sales Person',
        };
    }
}
