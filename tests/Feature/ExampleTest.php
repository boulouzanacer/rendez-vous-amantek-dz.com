<?php

use App\Models\User;

it('redirects guests to the login screen', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('redirects authenticated users to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
});
