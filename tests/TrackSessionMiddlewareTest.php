<?php

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use ProAI\Footprint\Middleware\TrackSession;
use ProAI\Footprint\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

it('does nothing when there is no session', function () {
    $request = Request::create('/test');
    $middleware = $this->app->make(TrackSession::class);

    $response = $middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('does nothing when there is no authenticated user', function () {
    $request = Request::create('/test');
    $request->setLaravelSession($this->app['session.store']);

    $middleware = $this->app->make(TrackSession::class);

    $response = $middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('does not update activity within refresh interval', function () {
    Auth::guard('footprint')->login($this->user);

    $session = $this->user->currentSession();
    $originalTime = $session->last_used_at->copy();

    $request = Request::create('/test');
    $request->setLaravelSession($this->app['session.store']);
    $request->setUserResolver(fn () => $this->user);

    $middleware = $this->app->make(TrackSession::class);
    $middleware->handle($request, fn ($req) => response('ok'));

    $session->refresh();
    expect($session->last_used_at->eq($originalTime))->toBeTrue();
});

it('updates activity after refresh interval has passed', function () {
    Carbon::setTestNow(now()->subMinutes(10));
    Auth::guard('footprint')->login($this->user);
    Carbon::setTestNow();

    $session = $this->user->currentSession();
    $originalTime = $session->last_used_at->copy();

    $request = Request::create('/test');
    $request->setLaravelSession($this->app['session.store']);
    $request->setUserResolver(fn () => $this->user);

    $middleware = $this->app->make(TrackSession::class);
    $middleware->handle($request, fn ($req) => response('ok'));

    $session->refresh();
    expect($session->last_used_at->gt($originalTime))->toBeTrue();
});
