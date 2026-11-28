<?php

namespace App\Http\Requests;

use App\Enums\EmployeePosition;
use App\Enums\EmployeeStatus;
use App\Enums\TaskPriority;
use App\Enums\WorkStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectTaskRequest extends FormRequest
{
    /**
     * Fields that only a team leader may change on a task.
     *
     * @var list<string>
     */
    private const LEADER_ONLY_FIELDS = [
        'name',
        'priority',
        'status',
        'progress',
        'emp_id',
        'assigned_by_id',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $user = $user instanceof User ? $user : User::currentWorkspaceUser();

        if ($user === null || $user->isLeader()) {
            return true;
        }

        foreach (self::LEADER_ONLY_FIELDS as $field) {
            if ($this->exists($field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'emp_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->where('status', EmployeeStatus::Active->value)],
            'assigned_by_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('position', EmployeePosition::Leader->value)->where('status', EmployeeStatus::Active->value)],
            'status' => ['sometimes', Rule::enum(WorkStatus::class)],
            'progress' => ['sometimes', 'numeric', 'between:0,100'],
            'est_hours' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emp_id.required' => 'Choose who the task is assigned to.',
            'emp_id.exists' => 'Choose an active employee.',
            'assigned_by_id.exists' => 'Only an active leader can assign tasks.',
        ];
    }
}
