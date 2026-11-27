<?php

namespace App\Http\Requests;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskIssueNoteRequest extends FormRequest
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
            'category' => ['required', 'string', Rule::in(config('axis.issue_categories'))],
            'severity' => ['required', Rule::enum(IssueSeverity::class)],
            'status' => ['required', Rule::enum(IssueStatus::class)],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'encountered' => ['required', 'string', 'max:10000'],
            'resolution' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
