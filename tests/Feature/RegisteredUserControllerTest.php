<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Hash;

test('renders the create account form inside the login card', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('<section class="axis-modal w-full max-w-md overflow-hidden" aria-labelledby="login-title">', false)
        ->assertSee('Create account')
        ->assertSee('action="'.route('register.store').'"', false)
        ->assertSee('name="name"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertSee('href="'.route('login').'"', false);
});

test('redirects signed-in users away from the create account form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect(RouteServiceProvider::HOME);
});

test('creates an active account signs it in and redirects to the dashboard', function () {
    $this->post(route('register.store'), [
        'name' => 'New Member',
        'email' => ' NEW.MEMBER@EOCAMBO.DEV ',
        'password' => 'Smith##12345',
        'password_confirmation' => 'Smith##12345',
    ])->assertRedirect(RouteServiceProvider::HOME);

    $user = User::query()->where('email', 'new.member@eocambo.dev')->firstOrFail();

    expect($user->name)->toBe('New Member')
        ->and($user->can_login)->toBeTrue()
        ->and(Hash::check('Smith##12345', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('rejects a duplicate email regardless of casing', function () {
    User::factory()->create(['email' => 'taken@eocambo.dev']);

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'Another Member',
            'email' => ' TAKEN@EOCAMBO.DEV ',
            'password' => 'Smith##12345',
            'password_confirmation' => 'Smith##12345',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('rejects invalid registration details', function (array $payload, string $field) {
    $this->from(route('register'))
        ->post(route('register.store'), $payload)
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors($field);

    $this->assertGuest();
})->with([
    'missing name' => [
        ['email' => 'new@example.com', 'password' => 'Smith##12345', 'password_confirmation' => 'Smith##12345'],
        'name',
    ],
    'invalid email' => [
        ['name' => 'New Member', 'email' => 'not-an-email', 'password' => 'Smith##12345', 'password_confirmation' => 'Smith##12345'],
        'email',
    ],
    'short password' => [
        ['name' => 'New Member', 'email' => 'new@example.com', 'password' => 'short', 'password_confirmation' => 'short'],
        'password',
    ],
    'mismatched confirmation' => [
        ['name' => 'New Member', 'email' => 'new@example.com', 'password' => 'Smith##12345', 'password_confirmation' => 'Different##123'],
        'password',
    ],
]);
