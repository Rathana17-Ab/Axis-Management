<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;

test('shows the sign-in form to guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="login-title">', false)
        ->assertSee('Axis Management')
        ->assertSee('Project hub')
        ->assertSee('Sign in')
        ->assertSee('action="'.route('login.store').'"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('Switch user');
});

test('redirects signed-in users away from the sign-in form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(RouteServiceProvider::HOME);
});

test('signs in with valid credentials and redirects to the dashboard', function () {
    $user = User::factory()->create(['email' => 'ti.tong@eocambo.dev']);

    $this->post(route('login.store'), [
        'email' => ' TI.TONG@EOCAMBO.DEV ',
        'password' => 'password',
    ])->assertRedirect(RouteServiceProvider::HOME);

    $this->assertAuthenticatedAs($user);
});

test('always signs in to the dashboard even when another page was requested first', function () {
    $user = User::factory()->create(['email' => 'intended@eocambo.dev']);

    $this->get(route('admin.team.index'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(RouteServiceProvider::HOME);

    $this->assertAuthenticatedAs($user);
});

test('rejects an incorrect password', function () {
    User::factory()->create([
        'email' => 'wrong-password@eocambo.dev',
        'password' => 'correct-password',
    ]);

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'wrong-password@eocambo.dev',
            'password' => 'not-the-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'The provided credentials do not match our records.']);

    $this->assertGuest();
});

test('rejects a locked account', function () {
    User::factory()->locked()->create(['email' => 'locked@eocambo.dev']);

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'locked@eocambo.dev',
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('requires an email and password', function () {
    $this->from(route('login'))
        ->post(route('login.store'), [])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

test('rejects a malformed email array without authenticating', function () {
    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => ['not-an-email'],
            'password' => 'password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('limits sign-in attempts per IP address even when the email changes', function () {
    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->post(route('login.store'), [
            'email' => "throttle-{$attempt}@eocambo.dev",
            'password' => 'not-the-password',
        ])->assertFound();
    }

    $this->post(route('login.store'), [
        'email' => 'throttle-31@eocambo.dev',
        'password' => 'not-the-password',
    ])->assertTooManyRequests();
});

test('logs out and requires sign-in again for workspace pages', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});
