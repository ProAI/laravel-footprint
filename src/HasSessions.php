<?php

namespace ProAI\Footprint;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ProAI\Footprint\Contracts\Sessionable;

trait HasSessions
{
    /**
     * The current session instance.
     *
     * @var \ProAI\Footprint\Contracts\Sessionable|null
     */
    protected ?Sessionable $currentSession = null;

    /**
     * Get the access tokens that belong to model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Illuminate\Database\Eloquent\Model&\ProAI\Footprint\Contracts\Sessionable, $this>
     */
    public function sessions(): HasMany
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model&\ProAI\Footprint\Contracts\Sessionable> $model */
        $model = config('footprint.session_model');

        return $this->hasMany($model);
    }

    /**
     * Set the current session for this user.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model  $session
     * @return $this
     */
    public function withSession(Sessionable $session): static
    {
        $this->currentSession = $session;

        $session->setRelation('user', $this);

        return $this;
    }

    /**
     * Get the current session for this user.
     *
     * @return \ProAI\Footprint\Contracts\Sessionable|null
     */
    public function currentSession(): ?Sessionable
    {
        return $this->currentSession;
    }
}
