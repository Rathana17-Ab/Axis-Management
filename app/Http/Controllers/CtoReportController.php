<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCtoReportRequest;
use App\Http\Requests\UpdateCtoReportRequest;
use App\Http\Resources\CtoReportResource;
use App\Models\CtoReport;
use Illuminate\Http\JsonResponse;

class CtoReportController extends Controller
{
    private const DEFAULT_RECIPIENT = 'Dear Bong and team';

    private const DEFAULT_BLOCKER = 'None';

    private const DEFAULT_HOURS = 8;

    private const UNKNOWN_HANDLER = 'Unknown Developer';

    /**
     * Submit a daily or weekly CTO standup report as the acting team member.
     *
     * Filing a report is a personal action available to every signed-in member;
     * managing existing reports remains guarded by the record gates below.
     */
    public function store(StoreCtoReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $report = CtoReport::create(
            $this->normalizeReportFields($validated, true, $this->actingMember()?->name)
        );

        return CtoReportResource::make($report)->response()->setStatusCode(201);
    }

    /**
     * Display a specific report.
     */
    public function show(CtoReport $report): JsonResponse
    {
        $this->authorizeWorkspaceRecord('view-record', $report);

        return CtoReportResource::make($report)->response();
    }

    /**
     * Update an existing report.
     */
    public function update(UpdateCtoReportRequest $request, CtoReport $report): JsonResponse
    {
        $this->authorizeWorkspaceRecord('update-record', $report);

        $report->update(
            $this->normalizeReportFields($request->validated(), false, $this->actingMember()?->name)
        );

        return CtoReportResource::make($report->fresh())->response();
    }

    /**
     * Remove the specified report from storage.
     */
    public function destroy(CtoReport $report): JsonResponse
    {
        $this->authorizeWorkspaceRecord('update-record', $report);

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully.',
        ]);
    }

    /**
     * Keep not-null columns valid while preserving partial updates.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeReportFields(array $validated, bool $applyDefaults, ?string $actingName): array
    {
        $hasField = fn (string $key): bool => $applyDefaults || array_key_exists($key, $validated);

        if ($hasField('recipient') && ! filled($validated['recipient'] ?? null)) {
            $validated['recipient'] = self::DEFAULT_RECIPIENT;
        }

        if ($hasField('report_date') && ! filled($validated['report_date'] ?? null)) {
            $validated['report_date'] = today();
        }

        if ($hasField('handler_name') && ! filled($validated['handler_name'] ?? null)) {
            $validated['handler_name'] = $actingName ?? self::UNKNOWN_HANDLER;
        }

        if ($hasField('blocker') && ! filled($validated['blocker'] ?? null)) {
            $validated['blocker'] = self::DEFAULT_BLOCKER;
        }

        if ($hasField('hours_logged') && ! filled($validated['hours_logged'] ?? null)) {
            $validated['hours_logged'] = self::DEFAULT_HOURS;
        }

        return $validated;
    }
}
