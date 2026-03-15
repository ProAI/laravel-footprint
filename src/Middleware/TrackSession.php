<?php

namespace ProAI\Footprint\Middleware;

use Closure;
use Illuminate\Http\Request;
use ProAI\Footprint\Contracts\UserSessionRepository;

class TrackSession
{
    /**
     * The user session repository implementation.
     *
     * @var \ProAI\Footprint\Contracts\UserSessionRepository
     */
    protected UserSessionRepository $repository;

    /**
     * Create a new middleware instance.
     *
     * @param  \ProAI\Footprint\Contracts\UserSessionRepository  $repository
     */
    public function __construct(UserSessionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if (! $request->hasSession()) {
            return $response;
        }

        /** @var (\Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions)|null $user */
        $user = $request->user();

        $session = $user?->currentSession();

        if (! $session) {
            return $response;
        }

        /** @var int $refreshInterval */
        $refreshInterval = config('footprint.refresh_interval', 5);

        $threshold = now()->subMinutes($refreshInterval);

        /** @var \Illuminate\Support\Carbon $lastUsedAt */
        $lastUsedAt = $session->last_used_at;

        if ($lastUsedAt->lt($threshold)) {
            $this->repository->updateActivity($session);
        }

        return $response;
    }
}
