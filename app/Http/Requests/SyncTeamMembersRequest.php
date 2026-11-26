<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SyncTeamMembersRequest extends WorkspaceFormRequest
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
            'members' => ['required', 'array'],
            'members.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }
}
