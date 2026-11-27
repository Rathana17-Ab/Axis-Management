<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Show the form for requesting a password reset link.
     */
    public function showRequestForm(): View
    {
        return view('axis.passwords.email');
    }

    /**
     * Email a password reset link to the matching Axis account.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $this->normalizeEmailInput($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $status = Password::sendResetLink([
            'email' => $this->normalizeEmail($validated['email']),
        ]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    /**
     * Show the password reset form included in a reset link.
     */
    public function showResetForm(Request $request, string $token): View
    {
        $email = $request->input('email');

        return view('axis.passwords.reset', [
            'email' => is_string($email) ? $email : '',
            'token' => $token,
        ]);
    }

    /**
     * Replace the account password after validating the reset token.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $this->normalizeEmailInput($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = Password::reset(
            [
                'email' => $this->normalizeEmail($validated['email']),
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    /**
     * Normalize the submitted email before validation and broker lookups.
     */
    private function normalizeEmailInput(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => $this->normalizeEmail($email)]);
        }
    }

    /**
     * Normalize an email address for broker lookups.
     */
    private function normalizeEmail(string $email): string
    {
        return Str::transliterate(Str::lower(trim($email)));
    }
}
