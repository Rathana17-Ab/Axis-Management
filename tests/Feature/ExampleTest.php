<?php

use App\Providers\RouteServiceProvider;

test('the application root redirects to the Axis dashboard', function () {
    $this->get('/')->assertRedirect('/admin/dashboard');
});

test('the post-authentication destination is the dashboard', function () {
    expect(RouteServiceProvider::HOME)->toBe('/admin/dashboard');
});
