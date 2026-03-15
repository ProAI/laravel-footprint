<?php

use Illuminate\Support\Facades\Auth;
use ProAI\Footprint\Contracts\UserSessionRepository as UserSessionRepositoryContract;
use ProAI\Footprint\FootprintGuard;
use ProAI\Footprint\UserSessionRepository;

it('registers the footprint guard configuration', function () {
    $guardConfig = config('auth.guards.footprint');

    expect($guardConfig['driver'])->toBe('footprint');
});

it('merges footprint config', function () {
    expect(config('footprint.session_model'))->not->toBeNull();
    expect(config('footprint.remember_duration'))->toBe(43200);
    expect(config('footprint.rotate_on_login'))->toBeTrue();
    expect(config('footprint.refresh_interval'))->toBe(5);
    expect(config('footprint.expired_session_retention'))->toBe(1440);
});

it('binds the user session repository as singleton', function () {
    $repository1 = $this->app->make(UserSessionRepositoryContract::class);
    $repository2 = $this->app->make(UserSessionRepositoryContract::class);

    expect($repository1)->toBeInstanceOf(UserSessionRepository::class);
    expect($repository1)->toBe($repository2);
});

it('resolves the footprint guard', function () {
    $guard = Auth::guard('footprint');

    expect($guard)->toBeInstanceOf(FootprintGuard::class);
});
