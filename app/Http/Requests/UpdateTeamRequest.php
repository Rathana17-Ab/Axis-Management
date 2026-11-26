<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends WorkspaceFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->hasWorkspaceAccess(WorkspacePermission::TeamsManage);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($this->route('team'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
