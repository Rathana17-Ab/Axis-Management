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
     *
     * The Axis workspace is an open internal console (see routes/web.php).
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
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('projects', 'name')->ignore($this->route('project'))],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(WorkStatus::class)],
        ];
    }
}
