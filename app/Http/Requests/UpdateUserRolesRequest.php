<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateUserRolesRequest extends WorkspaceFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->hasWorkspaceAccess(WorkspacePermission::UsersManage);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array'],
            'roles.*' => [Rule::in(array_map(fn (WorkspaceRole $role): string => $role->value, WorkspaceRole::cases()))],
        ];
    }
}
