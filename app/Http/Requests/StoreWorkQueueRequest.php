<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreWorkQueueRequest extends WorkspaceFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assignment_rule' => ['nullable', 'string', 'max:255'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
