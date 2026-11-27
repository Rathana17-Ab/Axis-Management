<?php

namespace App\Http\Requests;

use App\Enums\EmployeePosition;
use App\Enums\EmployeeStatus;
use App\Enums\TaskPriority;
use App\Enums\WorkStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'emp_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', EmployeeStatus::Active->value)],
            'assigned_by_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('position', EmployeePosition::Leader->value)->where('status', EmployeeStatus::Active->value)],
            'status' => ['sometimes', Rule::enum(WorkStatus::class)],
            'progress' => ['sometimes', 'numeric', 'between:0,100'],
            'est_hours' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
            'initial_issue' => ['sometimes', 'nullable', 'array'],
            'initial_issue.encountered' => ['required_with:initial_issue', 'string', 'max:10000'],
            'initial_issue.resolution' => ['nullable', 'string', 'max:10000'],
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
