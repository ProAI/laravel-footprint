<?php

namespace ProAI\Footprint\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface UserSessionRepository
{
    /**
     * Create a user session.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions  $user
     * @param  string|null  $token
     * @return \ProAI\Footprint\Contracts\Sessionable
     */
    public function create(Authenticatable $user, #[\SensitiveParameter] ?string $token): Sessionable;

    /**
     * Retrieve a user session by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \ProAI\Footprint\Contracts\Sessionable|null
     */
    public function getById(mixed $identifier): ?Sessionable;

    /**
     * Retrieve a user session by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \ProAI\Footprint\Contracts\Sessionable|null
     */
    public function getByToken(mixed $identifier, #[\SensitiveParameter] string $token): ?Sessionable;

    /**
     * Update session activity (e.g. last_used_at).
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable  $session
     * @return void
     */
    public function updateActivity(Sessionable $session): void;

    /**
     * Update the "remember me" token for the given user session in storage.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable  $session
     * @param  string|null  $token
     * @return void
     */
    public function updateRememberToken(Sessionable $session, #[\SensitiveParameter] ?string $token): void;

    /**
     * Delete a user session.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable  $session
     * @return void
     */
    public function delete(Sessionable $session): void;

    /**
     * Delete all sessions for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions  $user
     * @param  bool  $exceptCurrent
     * @return void
     */
    public function deleteAllFor(Authenticatable $user, bool $exceptCurrent = false): void;

    /**
     * Delete all expired sessions.
     *
     * @param  int|null  $retentionPeriod
     * @return void
     */
    public function deleteExpired(?int $retentionPeriod = null): void;
}
