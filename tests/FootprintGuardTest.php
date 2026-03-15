<?php

use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use ProAI\Footprint\FootprintGuard;
use ProAI\Footprint\Tests\Fixtures\User;
use ProAI\Footprint\UserSession;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

it('resolves the footprint guard', function () {
    expect(Auth::guard('footprint'))->toBeInstanceOf(FootprintGuard::class);
});

it('returns null when no user is authenticated', function () {
    expect(Auth::guard('footprint')->user())->toBeNull();
    expect(Auth::guard('footprint')->id())->toBeNull();
});

it('logs in a user without remember', function () {
    Event::fake();

    Auth::guard('footprint')->login($this->user);

    expect(Auth::guard('footprint')->user())->not->toBeNull();
    expect(Auth::guard('footprint')->id())->toBe($this->user->id);
    expect($this->user->currentSession())->not->toBeNull();
    expect($this->user->currentSession()->isRemembered())->toBeFalse();

    Event::assertDispatched(Login::class);
});

it('logs in a user with remember', function () {
    Auth::guard('footprint')->login($this->user, remember: true);

    expect($this->user->currentSession()->isRemembered())->toBeTrue();
});

it('creates a user session record on login', function () {
    Auth::guard('footprint')->login($this->user);

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(1);
});

it('stores the session id in the Laravel session', function () {
    Auth::guard('footprint')->login($this->user);

    $sessionName = Auth::guard('footprint')->getName();
    $storedId = session()->get($sessionName);

    expect($storedId)->toBe($this->user->currentSession()->getIdentifier());
});

it('logs out all devices by default', function () {
    Event::fake();

    // Create multiple sessions
    Auth::guard('footprint')->login($this->user);
    $session2 = UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(2);

    Auth::guard('footprint')->logout();

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(0);
    expect(Auth::guard('footprint')->user())->toBeNull();

    Event::assertDispatched(Logout::class);
});

it('logs out current device only when configured', function () {
    Event::fake();

    $this->app['config']->set('footprint.logout_all_devices', false);

    // Re-resolve guard with new config
    Auth::forgetGuards();

    Auth::guard('footprint')->login($this->user);
    $session2 = UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(2);

    Auth::guard('footprint')->logout();

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(1);

    Event::assertDispatched(CurrentDeviceLogout::class);
});

it('can logout a specific device', function () {
    Auth::guard('footprint')->login($this->user);

    $otherSession = UserSession::forceCreate([
        'user_id' => $this->user->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    Auth::guard('footprint')->logoutDevice($otherSession);

    expect(UserSession::find($otherSession->id))->toBeNull();
    // Current session should still exist
    expect(Auth::guard('footprint')->user())->not->toBeNull();
});

it('throws exception when logging out device for different user', function () {
    Auth::guard('footprint')->login($this->user);

    $otherUser = User::create([
        'name' => 'Other User',
        'email' => 'other@example.com',
        'password' => bcrypt('password'),
    ]);

    $otherSession = UserSession::forceCreate([
        'user_id' => $otherUser->id,
        'last_used_at' => now(),
        'remember_issued_at' => now(),
    ]);

    Auth::guard('footprint')->logoutDevice($otherSession);
})->throws(LogicException::class);

it('can logout other devices', function () {
    Auth::guard('footprint')->login($this->user);

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

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(3);

    Auth::guard('footprint')->logoutOtherDevices();

    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(1);
    expect(Auth::guard('footprint')->user())->not->toBeNull();
});

it('throws exception when logging out current device without session', function () {
    // Login and then remove the session reference
    Auth::guard('footprint')->login($this->user);

    // Manually clear the current session on the user
    $reflection = new ReflectionProperty($this->user, 'currentSession');
    $reflection->setAccessible(true);
    $reflection->setValue($this->user, null);

    Auth::guard('footprint')->logoutCurrentDevice();
})->throws(LogicException::class);

it('can authenticate via session id', function () {
    Auth::guard('footprint')->login($this->user);

    $sessionId = $this->user->currentSession()->getIdentifier();

    // Simulate a new request by clearing the cached user
    $guard = Auth::guard('footprint');
    $reflection = new ReflectionProperty($guard, 'user');
    $reflection->setAccessible(true);
    $reflection->setValue($guard, null);

    // The session should still have the user session id
    $user = Auth::guard('footprint')->user();

    expect($user)->not->toBeNull();
    expect($user->id)->toBe($this->user->id);
});

it('returns null when session is deleted', function () {
    Auth::guard('footprint')->login($this->user);

    $sessionId = $this->user->currentSession()->getIdentifier();

    // Delete the session from DB
    UserSession::destroy($sessionId);

    // Clear cached user
    $guard = Auth::guard('footprint');
    $reflection = new ReflectionProperty($guard, 'user');
    $reflection->setAccessible(true);
    $reflection->setValue($guard, null);

    expect(Auth::guard('footprint')->user())->toBeNull();
});
