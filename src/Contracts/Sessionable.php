<?php

namespace ProAI\Footprint\Contracts;

/**
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $remember_issued_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $last_used_at
 */
interface Sessionable
{
    /**
     * Get the name of the unique identifier for the user session.
     *
     * @return string
     */
    public function getIdentifierName(): string;

    /**
     * Get the unique identifier for the user session.
     *
     * @return mixed
     */
    public function getIdentifier(): mixed;

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getUserIdentifierName(): string;

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getUserIdentifier(): mixed;

    /**
     * Determine whether this session has a "remember me" token.
     *
     * @return bool
     */
    public function isRemembered(): bool;
}
