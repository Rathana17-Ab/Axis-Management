<?php

use App\Models\CtoReport;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

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
        ->assertJsonPath('data.hours', 38.5)
        ->assertJsonPath('data.canEdit', true);

    $this->assertDatabaseHas('cto_reports', ['handler_name' => 'Ti Tong', 'user_id' => $member->id]);
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
    $member = User::factory()->create(['name' => 'Own Reporter']);
    $this->actingAs($member);

    $report = CtoReport::factory()->authoredBy($member)->create([
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

it('keeps not-null report fields valid when the update clears them', function () {
    $this->freezeTime();
    $member = User::factory()->create(['name' => 'Signed-in Developer']);
    $this->actingAs($member);

    $report = CtoReport::factory()->authoredBy($member)->create([
        'recipient' => 'Dear Bong and team',
        'blocker' => 'None',
        'hours_logged' => 8,
    ]);

    $this->patchJson(route('admin.api.reports.update', $report), [
        'recipient' => null,
        'report_date' => null,
        'handler_name' => null,
        'blocker' => null,
        'hours_logged' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.recipient', 'Dear Bong and team')
        ->assertJsonPath('data.blocker', 'None')
        ->assertJsonPath('data.hours', 8);

    $report->refresh();
    expect($report->report_date->toDateString())->toBe(now()->toDateString())
        ->and($report->handler_name)->toBe('Signed-in Developer');
});

it('deletes a report', function () {
    $member = User::factory()->create(['name' => 'Own Reporter']);
    $this->actingAs($member);

    $report = CtoReport::factory()->authoredBy($member)->create();

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

it('lets a non-leader change only their own report', function () {
    $member = User::factory()->create(['name' => 'Own Reporter']);
    $other = User::factory()->create(['name' => 'Other Reporter']);
    $this->actingAs($member);

    $ownReport = CtoReport::factory()->authoredBy($member)->create(['summary' => 'Own summary']);
    $otherReport = CtoReport::factory()->authoredBy($other)->create(['summary' => 'Other summary']);

    $this->patchJson(route('admin.api.reports.update', $otherReport), ['summary' => 'Changed by someone else'])
        ->assertForbidden();
    $this->deleteJson(route('admin.api.reports.destroy', $otherReport))
        ->assertForbidden();

    $this->patchJson(route('admin.api.reports.update', $ownReport), ['summary' => 'Updated own summary'])
        ->assertOk()
        ->assertJsonPath('data.summary', 'Updated own summary');
    $this->deleteJson(route('admin.api.reports.destroy', $ownReport))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($otherReport->fresh()->summary)->toBe('Other summary')
        ->and(CtoReport::find($ownReport->id))->toBeNull();
});

it('lets a leader change another member report', function () {
    $leader = User::factory()->leader()->create(['name' => 'Team Leader']);
    $other = User::factory()->create(['name' => 'Other Reporter']);
    $this->actingAs($leader);

    $report = CtoReport::factory()->authoredBy($other)->create(['summary' => 'Original summary']);

    $this->patchJson(route('admin.api.reports.update', $report), ['summary' => 'Leader update'])
        ->assertOk()
        ->assertJsonPath('data.canEdit', true)
        ->assertJsonPath('data.summary', 'Leader update');
    $this->deleteJson(route('admin.api.reports.destroy', $report))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(CtoReport::find($report->id))->toBeNull();
});

it('marks only the acting member report as editable', function () {
    $member = User::factory()->create(['name' => 'Own Reporter']);
    $other = User::factory()->create(['name' => 'Other Reporter']);
    $this->actingAs($member);

    $ownReport = CtoReport::factory()->authoredBy($member)->create();
    $otherReport = CtoReport::factory()->authoredBy($other)->create();

    $this->getJson(route('admin.api.reports.show', $ownReport))
        ->assertOk()
        ->assertJsonPath('data.canEdit', true);
    $this->getJson(route('admin.api.reports.show', $otherReport))
        ->assertOk()
        ->assertJsonPath('data.canEdit', false);
});

it('lets a member change a report filed for them under their name', function () {
    $leader = User::factory()->leader()->create(['name' => 'Team Leader']);
    $member = User::factory()->create(['name' => 'Own Reporter']);
    $this->actingAs($member);

    $report = CtoReport::factory()->authoredBy($leader)->create([
        'handler_name' => 'Own Reporter',
        'summary' => 'Filed by leader',
    ]);

    $this->getJson(route('admin.api.reports.show', $report))
        ->assertOk()
        ->assertJsonPath('data.canEdit', true);
    $this->patchJson(route('admin.api.reports.update', $report), ['summary' => 'Updated by owner'])
        ->assertOk()
        ->assertJsonPath('data.summary', 'Updated by owner');
});

it('lets a member change a legacy report filed under their name', function () {
    $member = User::factory()->create(['name' => 'Legacy Reporter']);
    $this->actingAs($member);

    $report = CtoReport::factory()->create([
        'user_id' => null,
        'handler_name' => 'Legacy Reporter',
        'summary' => 'Legacy summary',
    ]);

    $this->patchJson(route('admin.api.reports.update', $report), ['summary' => 'Updated legacy summary'])
        ->assertOk()
        ->assertJsonPath('data.summary', 'Updated legacy summary');
});
