<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Open = 'Open';
    case Investigating = 'Investigating';
    case Resolved = 'Resolved';

    /**
     * The next status in the Open → Investigating → Resolved → Open cycle.
     */
    public function next(): self
    {
        return match ($this) {
            self::Open => self::Investigating,
            self::Investigating => self::Resolved,
            self::Resolved => self::Open,
        };
    }
}
