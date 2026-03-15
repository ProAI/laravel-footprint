<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use ProAI\Footprint\Tests\Fixtures\User;
use ProAI\Footprint\UserSession;

it('prunes expired sessions', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Create an expired session
    Carbon::setTestNow(now()->subDays(90));
    Auth::guard('footprint')->login($user);
    Carbon::setTestNow();

    $expiredSessionId = $user->currentSession()->getIdentifier();

    // Create a fresh session
    Auth::forgetGuards();
    Auth::guard('footprint')->login($user);
    $freshSessionId = $user->currentSession()->getIdentifier();

    expect(UserSession::count())->toBe(2);

    $this->artisan('footprint:prune-expired')
        ->assertExitCode(0);

    expect(UserSession::find($expiredSessionId))->toBeNull();
    expect(UserSession::find($freshSessionId))->not->toBeNull();
});

it('outputs success message', function () {
    $this->artisan('footprint:prune-expired')
        ->expectsOutputToContain('Expired user sessions pruned successfully')
        ->assertExitCode(0);
});
