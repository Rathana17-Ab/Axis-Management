<?php

namespace App\Models;

use App\Enums\ReportType;
use Database\Factories\CtoReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'user_id',
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

    /**
     * The team member who filed the report.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Whether the member may change this report.
     *
     * Leaders manage every report. Other members may change reports they filed
     * or reports listed under their own name, including older records without
     * an author reference.
     */
    public function isEditableBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isLeader()) {
            return true;
        }

        if ($this->user_id !== null && (int) $this->user_id === $user->id) {
            return true;
        }

        return filled($this->handler_name)
            && strcasecmp(trim((string) $this->handler_name), trim($user->name)) === 0;
    }
}
