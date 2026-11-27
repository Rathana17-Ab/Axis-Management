<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /** Session key holding the email that owns the pending verification code. */
    private const PENDING_EMAIL_SESSION_KEY = 'axis.password-reset.email';

    /**
     * Show the form for requesting a password reset code.
     */
    public function showRequestForm(): View
    {
        return view('axis.passwords.email');
    }

    /**
     * Email a six-digit verification code to the matching Axis account.
     */
    public function sendResetCode(Request $request): RedirectResponse
    {
        $this->normalizeEmailInput($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return $this->issueVerificationCode($request, $validated['email'], false);
    }

    /**
     * Email a fresh verification code from the verification form.
     */
    public function resendCode(Request $request): RedirectResponse
    {
        $this->normalizeEmailInput($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        return $this->issueVerificationCode($request, $validated['email'], true);
    }

    /**
     * Show the form for entering the emailed verification code.
     */
    public function showResetForm(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get(self::PENDING_EMAIL_SESSION_KEY);

        if (! is_string($email) || $email === '') {
            return redirect()->route('password.request');
        }

        return view('axis.passwords.reset', [
            'email' => $email,
            'resendSeconds' => $this->passwordConfigValue('throttle'),
            'expireMinutes' => $this->passwordConfigValue('expire'),
        ]);
    }

    /**
     * Replace the account password after validating the verification code.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $this->normalizeEmailInput($request);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $email = $this->normalizeEmail($validated['email']);
        $pendingEmail = $request->session()->get(self::PENDING_EMAIL_SESSION_KEY);

        if (! is_string($pendingEmail) || $this->normalizeEmail($pendingEmail) !== $email) {
            return back()->withInput($request->only('email'))
                ->withErrors(['code' => __('passwords.expired_request')]);
        }

        $user = $this->findUserByEmail($email);

        if ($user === null) {
            return back()->withInput($request->only('email'))
                ->withErrors(['code' => __('passwords.code')]);
        }

        $status = Password::reset(
            [
                'email' => $user->email,
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['code'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['code' => __('passwords.code')]);
        }

        $request->session()->forget(self::PENDING_EMAIL_SESSION_KEY);

        return redirect()->route('login')->with('status', __($status));
    }

    /**
     * Create and email a verification code, unless one was issued moments ago.
     */
    private function issueVerificationCode(Request $request, string $submittedEmail, bool $requirePendingEmail): RedirectResponse
    {
        $email = $this->normalizeEmail($submittedEmail);
        $pendingEmail = $request->session()->get(self::PENDING_EMAIL_SESSION_KEY);

        if ($requirePendingEmail && (! is_string($pendingEmail) || $this->normalizeEmail($pendingEmail) !== $email)) {
            return back()->withErrors(['email' => __('passwords.expired_request')]);
        }

        $user = $this->findUserByEmail($email);

        if ($user !== null && Password::getRepository()->recentlyCreatedToken($user)) {
            return back()->withInput(['email' => $user->email])
                ->withErrors([
                    'email' => __('passwords.resend_throttled', ['seconds' => $this->passwordConfigValue('throttle')]),
                ]);
        }

        if ($user !== null) {
            $email = $user->email;
            $code = Str::password(length: 6, letters: false, numbers: true, symbols: false, spaces: false);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );

            $user->notify(new PasswordResetCode($code));
        }

        $request->session()->put(self::PENDING_EMAIL_SESSION_KEY, $email);

        return redirect()->route('password.reset')
            ->with('status', __('passwords.sent'));
    }

    /**
     * Find an account without depending on the database collation's case sensitivity.
     */
    private function findUserByEmail(string $email): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    /**
     * Normalize the submitted email before validation and account lookups.
     */
    private function normalizeEmailInput(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => $this->normalizeEmail($email)]);
        }
    }

    /**
     * Normalize an email address for account lookups.
     */
    private function normalizeEmail(string $email): string
    {
        return Str::transliterate(Str::lower(trim($email)));
    }

    /**
     * Read a value from the configured password broker.
     */
    private function passwordConfigValue(string $key): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.'.$key);
    }
}
