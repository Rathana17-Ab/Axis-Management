<?php

use App\Enums\IssueStatus;
use App\Models\ProjectTask;
use App\Models\TaskIssueNote;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

describe('store', function () {
    it('logs a note authored by the acting team member', function () {
        $member = User::factory()->create(['name' => 'Phal Kristar']);
        $task = ProjectTask::factory()->create();

        $this->actingAs($member)
            ->postJson(route('admin.api.issues.store', $task), [
                'category' => 'API / DB Issue',
                'severity' => 'Critical',
                'status' => 'Open',
                'encountered' => 'Timer drift across tabs',
            ])
            ->assertCreated()
            ->assertJsonPath('data.author', 'Phal Kristar')
            ->assertJsonPath('data.type', 'API / DB Issue')
            ->assertJsonPath('data.resolution', '');

        expect($task->issueNotes()->count())->toBe(1);
    });

    it('returns 422 for an unknown category', function () {
        $task = ProjectTask::factory()->create();

        $this->postJson(route('admin.api.issues.store', $task), [
            'category' => 'Random',
            'severity' => 'High',
            'status' => 'Open',
            'encountered' => 'Something',
        ])->assertUnprocessable()->assertJsonValidationErrors('category');
    });

    it('returns 422 without the encountered description', function () {
        $task = ProjectTask::factory()->create();

        $this->postJson(route('admin.api.issues.store', $task), [
            'category' => 'Bug / Blocker',
            'severity' => 'High',
            'status' => 'Open',
        ])->assertUnprocessable()->assertJsonValidationErrors('encountered');
    });
});

it('updates the resolution text', function () {
    $note = TaskIssueNote::factory()->create();

    $this->patchJson(route('admin.api.issues.update', $note), ['resolution' => 'Scoped the rate limiter key'])
        ->assertOk()
        ->assertJsonPath('data.resolution', 'Scoped the rate limiter key');
});

it('clears the resolution back to null when the fix is removed', function () {
    $note = TaskIssueNote::factory()->create(['resolution' => 'Scoped the rate limiter key']);

    $this->patchJson(route('admin.api.issues.update', $note), ['resolution' => null])
        ->assertOk()
        ->assertJsonPath('data.resolution', '');

    expect($note->fresh()->resolution)->toBeNull();
});

it('cycles the issue to its next status', function () {
    $note = TaskIssueNote::factory()->resolved()->create();

    $this->postJson(route('admin.api.issues.cycle', $note))
        ->assertOk()
        ->assertJsonPath('data.status', 'Open');

    expect($note->fresh()->status)->toBe(IssueStatus::Open);
});

it('deletes the note', function () {
    $note = TaskIssueNote::factory()->create();

    $this->deleteJson(route('admin.api.issues.destroy', $note))->assertNoContent();

    $this->assertModelMissing($note);
});
