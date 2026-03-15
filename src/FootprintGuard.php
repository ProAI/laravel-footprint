<?php

namespace ProAI\Footprint;

use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use LogicException;
use ProAI\Footprint\Contracts\Sessionable as SessionableContract;
use ProAI\Footprint\Contracts\UserSessionRepository;
use Symfony\Component\HttpFoundation\Request;

class FootprintGuard extends SessionGuard
{
    /**
     * The user session repository implementation.
     *
     * @var \ProAI\Footprint\Contracts\UserSessionRepository
     */
    protected UserSessionRepository $repository;

    /**
     * Indicates if "remember me" tokens should be rotated on login.
     *
     * @var bool
     */
    protected bool $rotateOnLogin;

    /**
     * Indicates if the logout method should logout all devices.
     *
     * @var bool
     */
    protected bool $logoutAllDevices;

    /**
     * Create a new authentication guard.
     *
     * @param  string  $name
     * @param  \Illuminate\Contracts\Auth\UserProvider  $provider
     * @param  \ProAI\Footprint\Contracts\UserSessionRepository  $repository
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @param  \Symfony\Component\HttpFoundation\Request|null  $request
     * @param  \Illuminate\Support\Timebox|null  $timebox
     * @param  bool  $rehashOnLogin
     * @param  bool  $rotateOnLogin
     * @param  bool  $logoutAllDevices
     * @param  int  $timeboxDuration
     */
    public function __construct(
        string $name,
        UserProvider $provider,
        UserSessionRepository $repository,
        Session $session,
        ?Request $request = null,
        ?Timebox $timebox = null,
        bool $rehashOnLogin = true,
        bool $rotateOnLogin = true,
        bool $logoutAllDevices = true,
        int $timeboxDuration = 200000,
    ) {
        $this->repository = $repository;
        $this->rotateOnLogin = $rotateOnLogin;
        $this->logoutAllDevices = $logoutAllDevices;

        parent::__construct(
            $name,
            $provider,
            $session,
            $request,
            $timebox,
            $rehashOnLogin,
            $timeboxDuration
        );
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user(): ?AuthenticatableContract
    {
        if ($this->loggedOut) {
            return null;
        }

        if (! is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());

        if (! is_null($id)) {
            $session = $this->repository->getById($id);

            $this->user = $session ? $this->userFromSession($session) : null;

            if ($this->user) {
                $this->fireAuthenticatedEvent($this->user);
            }
        }

        if (is_null($this->user) && ! is_null($recaller = $this->recaller())) {
            $this->user = $this->userFromRecaller($recaller);

            if ($this->user) {
                /** @var \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions $currentUser */
                $currentUser = $this->user;

                /** @var \ProAI\Footprint\Contracts\Sessionable $currentSession */
                $currentSession = $currentUser->currentSession();

                $this->updateSession(
                    $currentSession->getIdentifier()
                );

                $this->fireLoginEvent($currentUser, true);
            }
        }

        return $this->user;
    }

    /**
     * Pull a user from the repository by its "remember me" cookie token.
     *
     * @param  \Illuminate\Auth\Recaller  $recaller
     * @return (\Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions)|null
     */
    protected function userFromRecaller($recaller): ?AuthenticatableContract // @pest-ignore-type
    {
        if (! $recaller->valid() || $this->recallAttempted) {
            return null;
        }

        $this->recallAttempted = true;

        $session = $this->repository->getByToken($recaller->id(), $recaller->token());

        $user = $session ? $this->userFromSession($session) : null;

        if ($user) {
            $token = $this->rotateOnLogin
                ? $this->createRememberToken()
                : $recaller->token();

            $this->rotateRememberToken($user, $token);
        }

        $this->viaRemember = ! is_null($user);

        return $user;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable  $session
     * @return (\Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions)|null
     */
    protected function userFromSession(SessionableContract $session): ?AuthenticatableContract
    {
        $user = $this->provider->retrieveById($session->getUserIdentifier());

        if (! $user) {
            return null;
        }

        /** @var \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions $user */
        $user->withSession($session);

        return $user;
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|string|null
     */
    public function id(): int|string|null
    {
        if ($this->user()) {
            return $this->user()->getAuthIdentifier();
        }

        return null;
    }

    /**
     * Log a user into the application.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions  $user
     * @param  bool  $remember
     * @return void
     */
    public function login(AuthenticatableContract $user, $remember = false): void // @pest-ignore-type
    {
        $token = $remember ? $this->createRememberToken() : null;
        $session = $this->repository->create($user, $token);

        $user->withSession($session);

        $this->updateSession($session->getIdentifier());

        if ($remember && $token !== null) {
            $this->queueRememberCookie($user, $token);
        }

        $this->fireLoginEvent($user, $remember);

        $this->setUser($user);
    }

    /**
     * Log the user out of the application.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->logoutAllDevices
            ? $this->logoutAllDevices()
            : $this->logoutCurrentDevice();
    }

    /**
     * Log the user out of the application on all devices.
     *
     * @return void
     */
    public function logoutAllDevices(): void
    {
        $user = $this->user();

        if ($user) {
            /** @var \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions $user */
            $this->repository->deleteAllFor($user);
        }

        $this->clearUserDataFromStorage();

        if ($user) {
            $this->events->dispatch(new Logout($this->name, $user));
        }

        $this->user = null;

        $this->loggedOut = true;
    }

    /**
     * Log the user out of the application on their current device only.
     *
     * This method does not cycle the "remember" token.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function logoutCurrentDevice(): void
    {
        $user = $this->user();

        if ($user) {
            /** @var \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions $user */
            $currentSession = $user->currentSession();

            if (! $currentSession) {
                throw new LogicException('Cannot logout current device, because authenticated user has no current session.');
            }

            $this->repository->delete($currentSession);
        }

        $this->clearUserDataFromStorage();

        if ($user) {
            $this->events->dispatch(new CurrentDeviceLogout($this->name, $user));
        }

        $this->user = null;

        $this->loggedOut = true;
    }

    /**
     * Log the user out of the application on the device of the given session.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable  $session
     * @return void
     *
     * @throws \LogicException
     */
    public function logoutDevice(SessionableContract $session): void
    {
        $user = $this->user();

        if (! $user || $user->getAuthIdentifier() !== $session->getUserIdentifier()) {
            throw new LogicException('Given user session does not match the authenticated user.');
        }

        $this->repository->delete($session);

        if ($user->currentSession()?->isRemembered()) {
            $token = $this->createRememberToken();
            $this->rotateRememberToken($user, $token);
        }

        $this->fireOtherDeviceLogoutEvent($user);
    }

    /**
     * Invalidate other sessions for the current user.
     *
     * The application must be using the AuthenticateSession middleware.
     *
     * @param  string|null  $password
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function logoutOtherDevices($password = null): ?AuthenticatableContract // @pest-ignore-type
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        /** @var \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions $user */
        $this->repository->deleteAllFor($user, exceptCurrent: true);

        if ($password) {
            $this->rehashUserPasswordForDeviceLogout($password);
        }

        if ($user->currentSession()?->isRemembered()) {
            $token = $this->createRememberToken();
            $this->rotateRememberToken($user, $token);
        }

        $this->fireOtherDeviceLogoutEvent($user);

        return $user;
    }

    /**
     * Rotate the "remember me" token.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions  $user
     * @param  string  $token
     * @return void
     */
    protected function rotateRememberToken(AuthenticatableContract $user, string $token): void
    {
        /** @var \ProAI\Footprint\Contracts\Sessionable $currentSession */
        $currentSession = $user->currentSession();

        $this->repository->updateRememberToken($currentSession, $token);

        $this->queueRememberCookie($user, $token);
    }

    /**
     * Create a new "remember me" token.
     *
     * @return string
     */
    protected function createRememberToken(): string
    {
        return Str::random(60);
    }

    /**
     * Queue the recaller cookie into the cookie jar.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions  $user
     * @param  string  $token
     * @return void
     */
    protected function queueRememberCookie(AuthenticatableContract $user, string $token): void
    {
        /** @var \ProAI\Footprint\Contracts\Sessionable $currentSession */
        $currentSession = $user->currentSession();

        $this->getCookieJar()->queue($this->createRecaller(
            $currentSession->getIdentifier().'|'.$token.'|'.$user->getAuthPassword()
        ));
    }
}
