<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('positions', 'name')->ignore($this->route('position'))],
            'code' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/', Rule::unique('positions', 'code')->ignore($this->route('position'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_leader' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
