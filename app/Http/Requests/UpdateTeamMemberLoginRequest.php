<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberLoginRequest extends FormRequest
{
    /**
     * The Axis workspace is an open internal console (see routes/web.php).
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
        /** @var User $member */
        $member = $this->route('user');

        return [
            'email' => [
                Rule::requiredIf(fn (): bool => blank($member->email)),
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($member->id),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
