<?php

namespace App\Http\Requests;

use App\Enums\EmployeePosition;
use App\Enums\EmployeeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
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
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', Rule::enum(EmployeePosition::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(EmployeeStatus::class)],
        ];
    }
}
