<?php

use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('renders the forgot password form for guests', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('<section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="password-request-title">', false)
        ->assertSee('Reset your password')
        ->assertSee('Send verification code')
        ->assertDontSee('Send reset link')
        ->assertSee('action="'.route('password.email').'"', false)
        ->assertSee('name="email"', false)
        ->assertSee('href="'.route('login').'"', false);
});

test('redirects signed-in users away from the password reset forms', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.request'))
        ->assertRedirect(RouteServiceProvider::HOME);

    $this->actingAs($user)
        ->get(route('password.reset'))
        ->assertRedirect(RouteServiceProvider::HOME);

    $this->actingAs($user)
        ->post(route('password.resend'), ['email' => $user->email])
        ->assertRedirect(RouteServiceProvider::HOME);
});

test('redirects guests to the request form when no code is pending', function () {
    $this->get(route('password.reset'))
        ->assertRedirect(route('password.request'));
});

test('emails a six-digit verification code and stores its hash', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset-code@eocambo.dev']);

    $this->post(route('password.email'), ['email' => ' RESET-CODE@EOCAMBO.DEV '])
        ->assertRedirect(route('password.reset'))
        ->assertSessionHas('status', __('passwords.sent'));

    $sentCode = null;

    Notification::assertSentTo($user, function (PasswordResetCode $notification) use (&$sentCode): bool {
        $sentCode = $notification->code;

        return true;
    });

    expect($sentCode)->toBeString();
    expect(strlen($sentCode))->toBe(6);

    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

    $storedHash = DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->value('token');

    expect($storedHash)->toBeString();
    expect(Hash::check($sentCode, (string) $storedHash))->toBeTrue();
});

test('does not reveal whether an account exists when requesting a code', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'unknown@eocambo.dev'])
        ->assertRedirect(route('password.reset'))
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertNothingSent();

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@eocambo.dev']);
});

test('requires a valid email when requesting a verification code', function (array $payload, string $field) {
    Notification::fake();

    $this->post(route('password.email'), $payload)->assertSessionHasErrors($field);

    Notification::assertNothingSent();
})->with([
    'missing email' => [[], 'email'],
    'malformed email' => [['email' => 'not-an-email'], 'email'],
]);

test('does not issue another code before the resend cooldown expires', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'cooldown-code@eocambo.dev']);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.reset'));

    $this->from(route('password.reset'))
        ->post(route('password.resend'), ['email' => $user->email])
        ->assertSessionHasErrors(['email' => __('passwords.resend_throttled', ['seconds' => 60])]);

    Notification::assertSentTimes(PasswordResetCode::class, 1);
});

test('resends a fresh verification code after the cooldown expires', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'resend-code@eocambo.dev']);

    $this->post(route('password.email'), ['email' => $user->email]);

    $this->travel(61)->seconds();

    $this->from(route('password.reset'))
        ->post(route('password.resend'), ['email' => $user->email])
        ->assertRedirect(route('password.reset'))
        ->assertSessionHas('status', __('passwords.sent'));

    $sentCodes = [];

    Notification::assertSentTo($user, function (PasswordResetCode $notification) use (&$sentCodes): bool {
        $sentCodes[] = $notification->code;

        return true;
    });

    expect($sentCodes)->toHaveCount(2);

    $storedHash = DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->value('token');

    expect(Hash::check((string) end($sentCodes), (string) $storedHash))->toBeTrue();
});

test('requires a pending request before resending a code', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'no-resend@eocambo.dev']);

    $this->from(route('password.request'))
        ->post(route('password.resend'), ['email' => $user->email])
        ->assertSessionHasErrors(['email' => __('passwords.expired_request')]);

    Notification::assertNothingSent();
});

test('does not resend a code to an email other than the pending one', function () {
    Notification::fake();

    $pendingUser = User::factory()->create(['email' => 'pending-resend@eocambo.dev']);
    $otherUser = User::factory()->create(['email' => 'other-resend@eocambo.dev']);

    $this->post(route('password.email'), ['email' => $pendingUser->email]);

    $this->from(route('password.reset'))
        ->post(route('password.resend'), ['email' => $otherUser->email])
        ->assertSessionHasErrors(['email' => __('passwords.expired_request')]);

    Notification::assertSentTimes(PasswordResetCode::class, 1);
});

test('throttles rapid verification code requests', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'throttled-code@eocambo.dev']);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.reset'));

    for ($attempt = 2; $attempt <= 5; $attempt++) {
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors(['email' => __('passwords.resend_throttled', ['seconds' => 60])]);
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertTooManyRequests();

    Notification::assertSentTimes(PasswordResetCode::class, 1);
});

test('renders the verification form after a code request', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'code-form@eocambo.dev']);

    $this->post(route('password.email'), ['email' => $user->email]);

    $this->get(route('password.reset'))
        ->assertOk()
        ->assertSee('<section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="password-reset-title">', false)
        ->assertSee('Choose a new password')
        ->assertSee('action="'.route('password.update').'"', false)
        ->assertSee('action="'.route('password.resend').'"', false)
        ->assertSee('Resend code')
        ->assertSee('Codes expire after 15 minutes. Wait 60 seconds between requests.')
        ->assertSee('name="email"', false)
        ->assertSee('name="code"', false)
        ->assertDontSee('name="token"', false)
        ->assertSee($user->email);
});

test('resets the password and redirects to sign in with a valid verification code', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'successful-code@eocambo.dev',
        'password' => Hash::make('Original##123'),
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    $sentCode = null;

    Notification::assertSentTo($user, function (PasswordResetCode $notification) use (&$sentCode): bool {
        $sentCode = $notification->code;

        return true;
    });

    $this->from(route('password.reset'))
        ->post(route('password.update'), [
            'email' => ' SUCCESSFUL-CODE@EOCAMBO.DEV ',
            'code' => $sentCode,
            'password' => 'Replacement##456',
            'password_confirmation' => 'Replacement##456',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __(Password::PASSWORD_RESET));

    $this->assertGuest();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

    expect(Hash::check('Replacement##456', $user->fresh()->password))->toBeTrue();
});

test('sends and accepts a code for any account with a usable email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'MixedCase@EOCAMBO.dev',
        'can_login' => false,
        'password' => Hash::make('Original##123'),
    ]);

    $this->post(route('password.email'), ['email' => 'mixedcase@eocambo.dev'])
        ->assertRedirect(route('password.reset'));

    $this->get(route('password.reset'))->assertSee('MixedCase@EOCAMBO.dev');

    $sentCode = null;

    Notification::assertSentTo($user, function (PasswordResetCode $notification) use (&$sentCode): bool {
        $sentCode = $notification->code;

        return true;
    });

    $this->from(route('password.reset'))
        ->post(route('password.update'), [
            'email' => 'MIXEDCASE@EOCAMBO.DEV',
            'code' => $sentCode,
            'password' => 'Replacement##456',
            'password_confirmation' => 'Replacement##456',
        ])
        ->assertRedirect(route('login'));

    expect(Hash::check('Replacement##456', $user->fresh()->password))->toBeTrue();
});

test('rejects an invalid verification code without changing the password', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'invalid-code@eocambo.dev',
        'password' => Hash::make('Original##123'),
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    $sentCode = null;

    Notification::assertSentTo($user, function (PasswordResetCode $notification) use (&$sentCode): bool {
        $sentCode = $notification->code;

        return true;
    });

    $wrongCode = str_pad((string) (((int) $sentCode + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->from(route('password.reset'))
        ->post(route('password.update'), [
            'email' => $user->email,
            'code' => $wrongCode,
            'password' => 'Replacement##456',
            'password_confirmation' => 'Replacement##456',
        ])
        ->assertSessionHasErrors(['code' => __('passwords.code')]);

    expect(Hash::check('Original##123', $user->fresh()->password))->toBeTrue();
});

test('rejects an expired verification code without changing the password', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'expired-code@eocambo.dev',
        'password' => Hash::make('Original##123'),
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    $sentCode = null;

    Notification::assertSentTo($user, function (PasswordResetCode $notification) use (&$sentCode): bool {
        $sentCode = $notification->code;

        return true;
    });

    $this->travel(16)->minutes();

    $this->from(route('password.reset'))
        ->post(route('password.update'), [
            'email' => $user->email,
            'code' => $sentCode,
            'password' => 'Replacement##456',
            'password_confirmation' => 'Replacement##456',
        ])
        ->assertSessionHasErrors(['code' => __('passwords.code')]);

    expect(Hash::check('Original##123', $user->fresh()->password))->toBeTrue();
});

test('rejects a code submission without a pending verification request', function () {
    $user = User::factory()->create([
        'email' => 'no-pending-code@eocambo.dev',
        'password' => Hash::make('Original##123'),
    ]);

    $this->from(route('login'))
        ->post(route('password.update'), [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'Replacement##456',
            'password_confirmation' => 'Replacement##456',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['code' => __('passwords.expired_request')]);

    expect(Hash::check('Original##123', $user->fresh()->password))->toBeTrue();
});

test('requires a valid code and matching new password', function (array $payload, string $field) {
    $this->post(route('password.update'), $payload)->assertSessionHasErrors($field);
})->with([
    'missing code' => [
        ['email' => 'validation@eocambo.dev', 'password' => 'Replacement##456', 'password_confirmation' => 'Replacement##456'],
        'code',
    ],
    'short password' => [
        ['email' => 'validation@eocambo.dev', 'code' => '123456', 'password' => 'short', 'password_confirmation' => 'short'],
        'password',
    ],
    'mismatched confirmation' => [
        ['email' => 'validation@eocambo.dev', 'code' => '123456', 'password' => 'Replacement##456', 'password_confirmation' => 'Different##789'],
        'password',
    ],
]);

test('renders the verification code email with its expiry period', function () {
    $user = User::factory()->make(['email' => 'email-content@eocambo.dev']);

    $rendered = (new PasswordResetCode('123456'))->toMail($user)->render();

    expect(str_contains($rendered, '123456'))->toBeTrue();
    expect(str_contains($rendered, '15 minutes'))->toBeTrue();
    expect(str_contains($rendered, 'Axis Management'))->toBeTrue();
    expect(str_contains($rendered, route('password.reset')))->toBeFalse();
});
