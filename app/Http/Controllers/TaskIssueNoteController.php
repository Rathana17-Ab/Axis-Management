<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskIssueNoteRequest;
use App\Http\Requests\UpdateTaskIssueNoteRequest;
use App\Http\Resources\TaskIssueNoteResource;
use App\Models\ProjectTask;
use App\Models\TaskIssueNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TaskIssueNoteController extends Controller
{
    /**
     * Log a developer encounter & fix note against a task.
     */
    public function store(StoreTaskIssueNoteRequest $request, ProjectTask $task): JsonResponse
    {
        $validated = $request->validated();

        $note = $task->issueNotes()->create([
            ...$validated,
            // Blank means "not fixed yet": store nothing rather than placeholder text
            'resolution' => filled($validated['resolution'] ?? null) ? $validated['resolution'] : null,
            'author_name' => $this->actingMember()?->name ?? 'Unknown',
        ]);

        return TaskIssueNoteResource::make($note)->response()->setStatusCode(201);
    }

    /**
     * Update the resolution text of an issue note.
     */
    public function update(UpdateTaskIssueNoteRequest $request, TaskIssueNote $issue): TaskIssueNoteResource
    {
        $resolution = $request->validated('resolution');
        $issue->update(['resolution' => filled($resolution) ? $resolution : null]);

        return TaskIssueNoteResource::make($issue);
    }

    /**
     * Cycle the issue status: Open → Investigating → Resolved → Open.
     */
    public function cycleStatus(TaskIssueNote $issue): TaskIssueNoteResource
    {
        $issue->update(['status' => $issue->status->next()]);

        return TaskIssueNoteResource::make($issue);
    }

    /**
     * Delete an issue note.
     */
    public function destroy(TaskIssueNote $issue): Response
    {
        $issue->delete();

        return response()->noContent();
    }
}
