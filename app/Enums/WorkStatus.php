<?php

namespace App\Enums;

/**
 * Shared status for projects, modules and tasks (CTO sheet: not yet start, processing, finish).
 */
enum WorkStatus: string
{
    case NotStarted = 'Not yet started';
    case Processing = 'Processing';
    case Finished = 'Finished';
}
