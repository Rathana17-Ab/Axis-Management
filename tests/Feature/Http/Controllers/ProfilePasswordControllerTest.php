<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Hash;

test('guests cannot change a password', function () {
    $this->patch(route('admin.profile.password.update'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('rejects an incorrect current password', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($user)
        ->from(route('admin.profile'))
        ->patch(route('admin.profile.password.update'), [
            'current_password' => 'not-the-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect(route('admin.profile'))
        ->assertSessionHasErrors(['current_password' => 'The password is incorrect.']);

    expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();
});

test('requires a confirmed new password of at least eight characters', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($user)
        ->from(route('admin.profile'))
        ->patch(route('admin.profile.password.update'), [
            'current_password' => 'old-password',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertRedirect(route('admin.profile'))
        ->assertSessionHasErrors(['password']);

    expect(Hash::check('old-password', $user->refresh()->password))->toBeTrue();
});

test('changes the password and accepts the new credentials on sign-in', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $this->actingAs($user)
        ->patch(route('admin.profile.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect(route('admin.profile'));

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'new-password',
    ])->assertRedirect(RouteServiceProvider::HOME);

    $this->assertAuthenticatedAs($user);
});
