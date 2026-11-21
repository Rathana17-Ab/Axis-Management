<?php

namespace App\Models;

use App\Enums\ReportType;
use Database\Factories\CtoReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'report_type',
    'recipient',
    'report_date',
    'date_from',
    'date_to',
    'handler_name',
    'module_name',
    'summary',
    'content_json',
    'blocker',
    'hours_logged',
])]
class CtoReport extends Model
{
    /** @use HasFactory<CtoReportFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'report_date' => 'date',
            'date_from' => 'date',
            'date_to' => 'date',
            'content_json' => 'array',
            'hours_logged' => 'float',
        ];
    }
}
