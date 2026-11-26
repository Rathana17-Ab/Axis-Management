<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateWorkQueueRequest extends WorkspaceFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->hasWorkspaceAccess(WorkspacePermission::WorkQueuesManage);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assignment_rule' => ['sometimes', 'nullable', 'string', 'max:255'],
            'team_id' => ['sometimes', 'nullable', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
