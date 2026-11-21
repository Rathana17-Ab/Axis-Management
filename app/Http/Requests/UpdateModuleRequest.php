<?php

namespace App\Http\Requests;

use App\Enums\WorkStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
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
            'project_id' => ['sometimes', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(WorkStatus::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'manager_name' => ['sometimes', 'string', 'max:255'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'team_members' => ['sometimes', 'array'],
            'team_members.*' => ['string', 'max:255', 'distinct'],
        ];
    }
}
