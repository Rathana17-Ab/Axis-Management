<?php

namespace App\Http\Resources;

use App\Http\Controllers\Controller;
use App\Models\CtoReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CtoReport
 */
class CtoReportResource extends JsonResource
{
    /**
     * Transform the resource into the shape consumed by the Axis workspace JS.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $requestUser = $request->user();
        $actingUser = $requestUser instanceof User ? $requestUser : Controller::currentWorkspaceUser();

        return [
            'id' => $this->id,
            'type' => $this->report_type->value,
            'recipient' => $this->recipient ?? 'Dear Bong and team',
            'date' => $this->report_date->toDateString(),
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'handler' => $this->handler_name,
            'module' => $this->module_name,
            'summary' => $this->summary,
            'content_json' => $this->content_json,
            'blocker' => $this->blocker,
            'hours' => $this->hours_logged,
            'canEdit' => $this->isEditableBy($actingUser),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
