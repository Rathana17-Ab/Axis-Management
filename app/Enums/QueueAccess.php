<?php

namespace App\Enums;

enum QueueAccess: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';
}
