<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests cannot upload a profile photo', function () {
    $this->post(route('admin.profile.photo.store'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('uploads a profile photo for the signed-in user', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $photo = UploadedFile::fake()->createWithContent(
        'avatar.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );

    $this->actingAs($user)
        ->post(route('admin.profile.photo.store'), ['photo' => $photo])
        ->assertRedirect(route('admin.profile'));

    $user->refresh();

    expect($user->avatar_path)->toStartWith('avatars/');

    Storage::disk('public')->assertExists($user->avatar_path);
});

test('rejects a file that is not an allowed image', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $photo = UploadedFile::fake()->create('avatar.txt', 1, 'text/plain');

    $this->actingAs($user)
        ->from(route('admin.profile'))
        ->post(route('admin.profile.photo.store'), ['photo' => $photo])
        ->assertRedirect(route('admin.profile'))
        ->assertSessionHasErrors('photo');

    expect($user->refresh()->avatar_path)->toBeNull();

    Storage::disk('public')->assertEmpty();
});

test('replaces the previous profile photo and deletes the old file', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/old.png']);
    Storage::disk('public')->put('avatars/old.png', 'old photo');

    $photo = UploadedFile::fake()->createWithContent(
        'new-avatar.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );

    $this->actingAs($user)
        ->post(route('admin.profile.photo.store'), ['photo' => $photo])
        ->assertRedirect(route('admin.profile'));

    $user->refresh();

    expect($user->avatar_path)->not->toBe('avatars/old.png');

    Storage::disk('public')->assertExists($user->avatar_path);
    Storage::disk('public')->assertMissing('avatars/old.png');
});

test('removes the profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/avatar.png']);
    Storage::disk('public')->put('avatars/avatar.png', 'photo');

    $this->actingAs($user)
        ->delete(route('admin.profile.photo.destroy'))
        ->assertRedirect(route('admin.profile'));

    expect($user->refresh()->avatar_path)->toBeNull();

    Storage::disk('public')->assertMissing('avatars/avatar.png');
});
