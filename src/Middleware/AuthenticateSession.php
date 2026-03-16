<?php

namespace ProAI\Footprint\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession as Middleware;

class AuthenticateSession extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next): mixed // @pest-ignore-type
    {
        if (! $request->hasSession() || ! $request->user()) {
            return tap($next($request), function () use ($request) {
                if (! is_null($this->guard()->user())) {
                    $this->storeFingerprintInSession($request);
                }
            });
        }

        if (! $this->verifyAuthentication($request)) {
            $this->logout($request);
        }

        return tap($next($request), function () use ($request) {
            if (! is_null($this->guard()->user())) {
                $this->storeFingerprintInSession($request);
            }
        });
    }

    /**
     * Verify the currently authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function verifyAuthentication(Request $request): bool
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions $user */
        $user = $request->user();

        if (! $user->currentSession()) {
            return false;
        }

        if (! $this->verifyAuthenticationViaRemember($request)) {
            return false;
        }

        if (! $request->session()->has($this->getFingerprintName())) {
            $this->storeFingerprintInSession($request);
        }

        /** @var string $storedFingerprint */
        $storedFingerprint = $request->session()->get($this->getFingerprintName());

        if (! hash_equals($storedFingerprint, $this->makeFingerprint($request))) {
            return false;
        }

        return true;
    }

    /**
     * If authenticated via remember, verify remember token and password hash.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function verifyAuthenticationViaRemember(Request $request): bool
    {
        if (! $this->guard()->viaRemember()) {
            return true;
        }

        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = $request->user();

        $passwordHash = $user->getAuthPassword();

        if (! $passwordHash) {
            return true;
        }

        /** @var string|null $cookie */
        $cookie = $request->cookies->get($this->guard()->getRecallerName());

        $cookiePasswordHash = explode('|', (string) $cookie)[2] ?? null;

        if (! $cookiePasswordHash || ! hash_equals($passwordHash, $cookiePasswordHash)) {
            return false;
        }

        return true;
    }

    /**
     * Store the user's fingerprint (password + remember token hash) in the session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function storeFingerprintInSession(Request $request): void
    {
        /** @var (\Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions)|null $user */
        $user = $request->user();

        if (! $user?->currentSession()) {
            return;
        }

        $request->session()->put([
            $this->getFingerprintName() => $this->makeFingerprint($request),
        ]);
    }

    /**
     * Get the name of the user's fingerprint in cache.
     *
     * @return string
     */
    protected function getFingerprintName(): string
    {
        return 'fingerprint_'.$this->auth->getDefaultDriver();
    }

    /**
     * Make the user's fingerprint (password + remember token hash) in the session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function makeFingerprint(Request $request): string
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions $user */
        $user = $request->user();

        /** @var \ProAI\Footprint\Contracts\Sessionable $currentSession */
        $currentSession = $user->currentSession();

        /** @var string|null $rememberTokenHash */
        $rememberTokenHash = $currentSession->remember_token;

        $passwordHash = $user->getAuthPassword();

        return $rememberTokenHash.'|'.$passwordHash;
    }
}
