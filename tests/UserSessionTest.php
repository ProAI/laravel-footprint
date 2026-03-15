<?php

use ProAI\Footprint\Tests\Fixtures\User;
use ProAI\Footprint\UserSession;

it('uses the user_sessions table', function () {
    $session = new UserSession;

    expect($session->getTable())->toBe('user_sessions');
});

it('has correct fillable attributes', function () {
    $session = new UserSession;

    expect($session->getFillable())->toBe([
        'user_id',
        'remember_token',
        'remember_issued_at',
        'ip_address',
        'user_agent',
        'last_used_at',
    ]);
});

it('hides remember_token from serialization', function () {
    $session = new UserSession;

    expect($session->getHidden())->toBe(['remember_token']);
});

it('casts last_used_at and remember_issued_at to datetime', function () {
    $session = new UserSession;

    expect($session->getCasts())->toMatchArray([
        'last_used_at' => 'datetime',
        'remember_issued_at' => 'datetime',
    ]);
});

it('returns the primary key as identifier name', function () {
    $session = new UserSession;

    expect($session->getIdentifierName())->toBe('id');
});

it('returns user_id as user identifier name', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $session = UserSession::forceCreate([
        'user_id' => $user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    expect($session->getUserIdentifierName())->toBe('user_id');
    expect($session->getUserIdentifier())->toBe($user->id);
});

it('returns the session identifier', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $session = UserSession::forceCreate([
        'user_id' => $user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    expect($session->getIdentifier())->toBe($session->id);
});

it('determines if session is remembered', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $sessionWithToken = UserSession::forceCreate([
        'user_id' => $user->id,
        'remember_token' => 'some-token',
        'remember_issued_at' => now(),
        'last_used_at' => now(),
    ]);

    $sessionWithoutToken = UserSession::forceCreate([
        'user_id' => $user->id,
        'remember_issued_at' => now(),
        'last_used_at' => now(),
    ]);

    expect($sessionWithToken->isRemembered())->toBeTrue();
    expect($sessionWithoutToken->isRemembered())->toBeFalse();
});
