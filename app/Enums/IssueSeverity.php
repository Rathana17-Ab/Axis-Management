<?php

namespace App\Enums;

enum IssueSeverity: string
{
    case Critical = 'Critical';
    case High = 'High';
    case Medium = 'Medium';
    case Low = 'Low';
}
