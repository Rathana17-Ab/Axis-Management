<?php

use App\Models\User;

test('does not expose public account creation', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'New Member',
        'email' => 'new.member@eocambo.dev',
        'password' => 'Smith##12345',
        'password_confirmation' => 'Smith##12345',
    ])->assertNotFound();

    expect(User::query()->where('email', 'new.member@eocambo.dev')->doesntExist())->toBeTrue();

    $this->assertGuest();
});

test('does not expose the create account form to signed-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/register')
        ->assertNotFound();
});
