<?php

use Illuminate\Support\Str;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '0712345678',
        'username' => 'testuser',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('domains table stores full domain on registration', function () {
    $username = 'My ISP Name';

    $response = $this->post('/register', [
        'name' => 'Acme ISP',
        'email' => 'acme@example.com',
        'phone' => '0712345679',
        'username' => $username,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = \App\Models\User::where('email', 'acme@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->tenant_id)->not->toBeNull();

    $tenant = \App\Models\Tenant::find($user->tenant_id);

    expect($tenant)->not->toBeNull();

    $baseSubdomain = Str::slug($username);
    $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    $expectedFullDomain = $baseSubdomain . '.' . $baseDomain;

    $this->assertDatabaseHas('domains', [
        'tenant_id' => $tenant->id,
        'domain' => $expectedFullDomain,
    ]);
});
