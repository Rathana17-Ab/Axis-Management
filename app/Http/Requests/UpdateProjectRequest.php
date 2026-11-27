<?php

namespace App\Http\Requests;

use App\Enums\WorkStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
        $startDate = $this->input('start_date') ?? $this->route('project')?->start_date?->toDateString();
        $deadlineRules = ['sometimes', 'nullable', 'date'];

        if ($startDate) {
            $deadlineRules[] = 'after_or_equal:'.$startDate;
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('projects', 'name')->ignore($this->route('project'))],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'deadline' => $deadlineRules,
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(WorkStatus::class)],
        ];
    }
}
