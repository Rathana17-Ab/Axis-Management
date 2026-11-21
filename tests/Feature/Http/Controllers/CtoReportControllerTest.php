<?php

use App\Models\CtoReport;
use App\Models\User;

it('submits a report as the acting team member dated today', function () {
    $this->freezeTime();
    $member = User::factory()->create(['name' => 'Ti Tong']);

    $this->actingAs($member)
        ->postJson(route('admin.api.reports.store'), [
            'report_type' => 'Weekly',
            'module_name' => 'Axis Sales',
            'summary' => 'Merged commission calculations.',
            'hours_logged' => 38.5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.handler', 'Ti Tong')
        ->assertJsonPath('data.date', now()->toDateString())
        ->assertJsonPath('data.blocker', 'None')
        ->assertJsonPath('data.hours', 38.5);
});

it('submits a structured weekly report with date range and sections', function () {
    $member = User::factory()->create(['name' => 'Kong Rathana']);

    $payload = [
        'report_type' => 'Weekly',
        'recipient' => 'Dear Bong and team',
        'report_date' => '2026-11-13',
        'date_from' => '2026-11-10',
        'date_to' => '2026-11-13',
        'module_name' => 'Axis Sales',
        'summary' => 'Weekly report on Axis Sales testing and fixes.',
        'hours_logged' => 30.0,
        'blocker' => 'None',
        'content_json' => [
            'sections' => [
                [
                    'title' => '1. Tested and Fixed on Team Lead Role',
                    'items' => [
                        [
                            'code' => 'TL 04',
                            'title' => 'Watch the team on the Team Sales Dashboard and Salesperson Performance',
                            'done_percent' => 100,
                            'issue_percent' => 0,
                            'sub_items' => [
                                ['code' => 'P58', 'title' => 'Team Sales Dashboard'],
                                ['code' => 'P59', 'title' => 'Salesperson Performance'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($member)
        ->postJson(route('admin.api.reports.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.recipient', 'Dear Bong and team')
        ->assertJsonPath('data.date_from', '2026-11-10')
        ->assertJsonPath('data.date_to', '2026-11-13')
        ->assertJsonPath('data.content_json.sections.0.title', '1. Tested and Fixed on Team Lead Role')
        ->assertJsonPath('data.hours', 30);
});

it('shows a report via API', function () {
    $report = CtoReport::factory()->create([
        'module_name' => 'Axis PMS',
        'hours_logged' => 15,
    ]);

    $this->getJson(route('admin.api.reports.show', $report))
        ->assertOk()
        ->assertJsonPath('data.id', $report->id)
        ->assertJsonPath('data.module', 'Axis PMS')
        ->assertJsonPath('data.hours', 15);
});

it('updates an existing report', function () {
    $report = CtoReport::factory()->create([
        'summary' => 'Initial summary',
        'hours_logged' => 8,
    ]);

    $this->patchJson(route('admin.api.reports.update', $report), [
        'summary' => 'Updated summary with more details',
        'hours_logged' => 12,
    ])
        ->assertOk()
        ->assertJsonPath('data.summary', 'Updated summary with more details')
        ->assertJsonPath('data.hours', 12);

    expect($report->fresh()->hours_logged)->toBe(12.0);
});

it('deletes a report', function () {
    $report = CtoReport::factory()->create();

    $this->deleteJson(route('admin.api.reports.destroy', $report))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(CtoReport::find($report->id))->toBeNull();
});

it('allows submitting a report for any specific developer name', function () {
    $this->postJson(route('admin.api.reports.store'), [
        'report_type' => 'Daily',
        'handler_name' => 'Phal Kristar',
        'module_name' => 'Axis POS',
        'summary' => 'Tested table layout and bill settlement.',
        'hours_logged' => 8,
    ])
        ->assertCreated()
        ->assertJsonPath('data.handler', 'Phal Kristar')
        ->assertJsonPath('data.module', 'Axis POS');
});

it('returns 422 when the summary is missing', function () {
    $this->postJson(route('admin.api.reports.store'), ['report_type' => 'Daily', 'module_name' => 'Axis Sales'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('summary');
});
