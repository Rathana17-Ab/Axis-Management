<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('renders the forgot password form for guests', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('<section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="password-request-title">', false)
        ->assertSee('Reset your password')
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
        ->get(route('password.reset', ['token' => 'reset-token', 'email' => $user->email]))
        ->assertRedirect(RouteServiceProvider::HOME);
});

test('renders the reset form with the emailed token', function () {
    $user = User::factory()->create(['email' => 'reset-form@eocambo.dev']);
    $token = Password::broker()->createToken($user);

    $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertSee('<section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="password-reset-title">', false)
        ->assertSee('Choose a new password')
        ->assertSee('action="'.route('password.update').'"', false)
        ->assertSee('name="token"', false)
        ->assertSee('value="'.$token.'"', false)
        ->assertSee($user->email);
});

test('emails a reset link to an existing account', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset-link@eocambo.dev']);

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => ' RESET-LINK@EOCAMBO.DEV '])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', __(Password::RESET_LINK_SENT));

    Notification::assertSentTo($user, ResetPassword::class);

    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
});

test('does not email a reset link to an unknown account', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => 'unknown@eocambo.dev'])
        ->assertSessionHasErrors(['email' => __(Password::INVALID_USER)]);

    Notification::assertNothingSent();
});

test('requires a valid email when requesting a reset link', function (array $payload, string $field) {
    Notification::fake();

    $this->post(route('password.email'), $payload)->assertSessionHasErrors($field);

    Notification::assertNothingSent();
})->with([
    'missing email' => [[], 'email'],
    'malformed email' => [['email' => 'not-an-email'], 'email'],
]);

test('resets the password and redirects to sign in with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'successful-reset@eocambo.dev',
        'password' => Hash::make('Original##123'),
    ]);
    $token = Password::broker()->createToken($user);

    $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->post(route('password.update'), [
            'token' => $token,
            'email' => ' SUCCESSFUL-RESET@EOCAMBO.DEV ',
            'password' => 'Replacement##456',
            'password_confirmation' => 'Replacement##456',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __(Password::PASSWORD_RESET));

    $this->assertGuest();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

    expect(Hash::check('Replacement##456', $user->fresh()->password))->toBeTrue();
});

test('rejects an invalid reset token without changing the password', function () {
    $user = User::factory()->create([
        'email' => 'invalid-token@eocambo.dev',
        'password' => Hash::make('Original##123'),
    ]);

    $this->from(route('login'))
        ->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'Replacement##456',
            'password_confirmation' => 'Replacement##456',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __(Password::INVALID_TOKEN)]);

    expect(Hash::check('Original##123', $user->fresh()->password))->toBeTrue();
});

test('requires a valid token and matching new password', function (array $payload, string $field) {
    $this->post(route('password.update'), $payload)->assertSessionHasErrors($field);
})->with([
    'missing token' => [
        ['email' => 'validation@eocambo.dev', 'password' => 'Replacement##456', 'password_confirmation' => 'Replacement##456'],
        'token',
    ],
    'short password' => [
        ['token' => 'reset-token', 'email' => 'validation@eocambo.dev', 'password' => 'short', 'password_confirmation' => 'short'],
        'password',
    ],
    'mismatched confirmation' => [
        ['token' => 'reset-token', 'email' => 'validation@eocambo.dev', 'password' => 'Replacement##456', 'password_confirmation' => 'Different##789'],
        'password',
    ],
]);
