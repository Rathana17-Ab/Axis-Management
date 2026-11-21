<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCtoReportRequest;
use App\Http\Requests\UpdateCtoReportRequest;
use App\Http\Resources\CtoReportResource;
use App\Models\CtoReport;
use Illuminate\Http\JsonResponse;

class CtoReportController extends Controller
{
    /**
     * Submit a daily or weekly CTO standup report as the acting team member.
     */
    public function store(StoreCtoReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $report = CtoReport::create([
            'report_type' => $validated['report_type'],
            'recipient' => $validated['recipient'] ?? 'Dear Bong and team',
            'report_date' => $validated['report_date'] ?? today(),
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'handler_name' => filled($validated['handler_name'] ?? null)
                ? $validated['handler_name']
                : ($this->actingMember()?->name ?? 'Unknown Developer'),
            'module_name' => $validated['module_name'],
            'summary' => $validated['summary'],
            'content_json' => $validated['content_json'] ?? null,
            'blocker' => filled($validated['blocker'] ?? null) ? $validated['blocker'] : 'None',
            'hours_logged' => $validated['hours_logged'] ?? 8,
        ]);

        return CtoReportResource::make($report)->response()->setStatusCode(201);
    }

    /**
     * Display a specific report.
     */
    public function show(CtoReport $report): JsonResponse
    {
        return CtoReportResource::make($report)->response();
    }

    /**
     * Update an existing report.
     */
    public function update(UpdateCtoReportRequest $request, CtoReport $report): JsonResponse
    {
        $validated = $request->validated();

        $report->update($validated);

        return CtoReportResource::make($report->fresh())->response();
    }

    /**
     * Remove the specified report from storage.
     */
    public function destroy(CtoReport $report): JsonResponse
    {
        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully.',
        ]);
    }
}
