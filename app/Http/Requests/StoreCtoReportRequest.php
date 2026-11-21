<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCtoReportRequest extends FormRequest
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
            'report_type' => ['required', Rule::enum(ReportType::class)],
            'recipient' => ['nullable', 'string', 'max:255'],
            'report_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'handler_name' => ['nullable', 'string', 'max:255'],
            'module_name' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:25000'],
            'content_json' => ['nullable', 'array'],
            'blocker' => ['nullable', 'string', 'max:255'],
            'hours_logged' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ];
    }
}
