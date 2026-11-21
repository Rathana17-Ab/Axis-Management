<?php

namespace App\Models;

use App\Enums\ReportType;
use Database\Factories\CtoReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtoReport extends Model
{
    /** @use HasFactory<CtoReportFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'report_type' => ReportType::class,
        'report_date' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',
        'content_json' => 'array',
        'hours_logged' => 'float',
    ];
}
