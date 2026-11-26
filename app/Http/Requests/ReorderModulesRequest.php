<?php

namespace App\Http\Requests;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderModulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Controller::currentWorkspaceUser();

        // An empty workspace keeps the original open-console behavior.
        return $user === null || Controller::canReorderModules($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct:strict', Rule::exists('modules', 'id')],
        ];
    }

    /**
     * A drag saves the complete list the member can see, so reject partial or stale orders.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $submitted = collect($this->input('ordered_ids', []))
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            $visible = Module::query()
                ->visibleTo(Controller::currentWorkspaceUser())
                ->pluck('modules.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($submitted !== $visible) {
                $validator->errors()->add('ordered_ids', 'The order must include every module you can see exactly once.');
            }
        }];
    }
}
