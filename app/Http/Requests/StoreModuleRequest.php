<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreModuleRequest extends FormRequest
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
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'customer_name' => ['required', 'string', 'max:255'],
            'manager_name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
