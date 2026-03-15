<?php

namespace ProAI\Footprint\Contracts;

interface HasSessions
{
    /**
     * Set the current session for this user.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable  $session
     * @return $this
     */
    public function withSession(Sessionable $session): static;

    /**
     * Get the current session for this user.
     *
     * @return \ProAI\Footprint\Contracts\Sessionable|null
     */
    public function currentSession(): ?Sessionable;
}
