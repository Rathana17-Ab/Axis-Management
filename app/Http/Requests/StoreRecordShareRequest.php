<?php

namespace App\Http\Requests;

use App\Enums\RecordShareAbility;
use App\Enums\WorkspacePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreRecordShareRequest extends WorkspaceFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->hasWorkspaceAccess(WorkspacePermission::RecordsManageAll);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shareable_type' => ['required', Rule::in(['project', 'module', 'task', 'report'])],
            'shareable_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'ability' => ['required', Rule::enum(RecordShareAbility::class)],
        ];
    }
}
