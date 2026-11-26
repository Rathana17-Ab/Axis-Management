<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    /**
     * The Axis workspace is an open internal console (see routes/web.php).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Derive a stable code from the name when one is not supplied.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => blank($this->input('code'))
                ? Str::slug($this->input('name', ''))
                : Str::lower($this->input('code')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')],
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/', Rule::unique('positions', 'code')],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_leader' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
