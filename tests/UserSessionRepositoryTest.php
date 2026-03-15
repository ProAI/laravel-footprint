<?php

use Illuminate\Support\Carbon;
use ProAI\Footprint\Contracts\UserSessionRepository as UserSessionRepositoryContract;
use ProAI\Footprint\Tests\Fixtures\User;
use ProAI\Footprint\UserSession;

beforeEach(function () {
    $this->repository = $this->app->make(UserSessionRepositoryContract::class);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

it('creates a user session without remember token', function () {
    $session = $this->repository->create($this->user, null);

    expect($session)->toBeInstanceOf(UserSession::class);
    expect($session->getUserIdentifier())->toBe($this->user->id);
    expect($session->remember_token)->toBeNull();
    expect($session->last_used_at)->not->toBeNull();
    expect($session->ip_address)->not->toBeNull();
});

it('creates a user session with remember token', function () {
    $token = 'test-remember-token';
    $session = $this->repository->create($this->user, $token);

    expect($session->remember_token)->toBe(hash('sha256', $token));
    expect($session->remember_issued_at)->not->toBeNull();
});

it('retrieves a session by id', function () {
    $session = $this->repository->create($this->user, null);

    $retrieved = $this->repository->getById($session->getIdentifier());

    expect($retrieved)->not->toBeNull();
    expect($retrieved->getIdentifier())->toBe($session->getIdentifier());
});

it('returns null for non-existent session id', function () {
    expect($this->repository->getById(999))->toBeNull();
});

it('does not retrieve expired sessions by id', function () {
    Carbon::setTestNow(now()->subDays(60));

    $session = $this->repository->create($this->user, null);

    Carbon::setTestNow();

    expect($this->repository->getById($session->getIdentifier()))->toBeNull();
});

it('retrieves a session by token', function () {
    $token = 'test-remember-token';
    $session = $this->repository->create($this->user, $token);

    $retrieved = $this->repository->getByToken($session->getIdentifier(), $token);

    expect($retrieved)->not->toBeNull();
    expect($retrieved->getIdentifier())->toBe($session->getIdentifier());
});

it('returns null for wrong token', function () {
    $session = $this->repository->create($this->user, 'correct-token');

    expect($this->repository->getByToken($session->getIdentifier(), 'wrong-token'))->toBeNull();
});

it('returns null for non-existent session when getting by token', function () {
    expect($this->repository->getByToken(999, 'any-token'))->toBeNull();
});

it('updates session activity', function () {
    Carbon::setTestNow(now()->subMinutes(10));
    $session = $this->repository->create($this->user, null);
    $originalTime = $session->last_used_at->copy();
    Carbon::setTestNow();

    $this->repository->updateActivity($session);

    $session->refresh();
    expect($session->last_used_at->gt($originalTime))->toBeTrue();
});

it('updates remember token', function () {
    $session = $this->repository->create($this->user, 'old-token');
    $oldToken = $session->remember_token;

    $this->repository->updateRememberToken($session, 'new-token');

    $session->refresh();
    expect($session->remember_token)->not->toBe($oldToken);
    expect($session->remember_token)->toBe(hash('sha256', 'new-token'));
});

it('deletes a session', function () {
    $session = $this->repository->create($this->user, null);
    $id = $session->getIdentifier();

    $this->repository->delete($session);

    expect(UserSession::find($id))->toBeNull();
});

it('deletes all sessions for a user', function () {
    $this->repository->create($this->user, null);
    $this->repository->create($this->user, null);
    $this->repository->create($this->user, null);

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(3);

    $this->repository->deleteAllFor($this->user);

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(0);
});

it('deletes all sessions except current for a user', function () {
    $session1 = $this->repository->create($this->user, null);
    $this->repository->create($this->user, null);
    $this->repository->create($this->user, null);

    $this->user->withSession($session1);

    $this->repository->deleteAllFor($this->user, exceptCurrent: true);

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(1);
    expect(UserSession::find($session1->getIdentifier()))->not->toBeNull();
});

it('deletes expired sessions', function () {
    // Create an expired session (old last_used_at, no remember token)
    Carbon::setTestNow(now()->subDays(90));
    $expiredSession = $this->repository->create($this->user, null);
    Carbon::setTestNow();

    // Create a fresh session
    $freshSession = $this->repository->create($this->user, null);

    $this->repository->deleteExpired(1440);

    expect(UserSession::find($expiredSession->getIdentifier()))->toBeNull();
    expect(UserSession::find($freshSession->getIdentifier()))->not->toBeNull();
});

it('creates a model instance', function () {
    $model = $this->repository->createModel();

    expect($model)->toBeInstanceOf(UserSession::class);
});
