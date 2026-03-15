<?php

use ProAI\Footprint\Tests\Fixtures\User;
use ProAI\Footprint\UserSession;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

it('returns null when no current session is set', function () {
    expect($this->user->currentSession())->toBeNull();
});

it('can set and get the current session', function () {
    $session = UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    $this->user->withSession($session);

    expect($this->user->currentSession())->toBe($session);
});

it('sets the user relation on the session when using withSession', function () {
    $session = UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    $this->user->withSession($session);

    expect($session->relationLoaded('user'))->toBeTrue();
    expect($session->user->id)->toBe($this->user->id);
});

it('returns a has many relationship for sessions', function () {
    UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    expect($this->user->sessions)->toHaveCount(2);
});

it('returns withSession fluently', function () {
    $session = UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    $result = $this->user->withSession($session);

    expect($result)->toBe($this->user);
});
