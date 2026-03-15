<?php

namespace ProAI\Footprint;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Sessionable
{
    /**
     * Get the user model that the user session belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var string $provider */
        $provider = config('auth.guards.footprint.provider', 'users');

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = config("auth.providers.{$provider}.model");

        return $this->belongsTo($model);
    }

    /**
     * Get the name of the unique identifier for the user session.
     *
     * @return string
     */
    public function getIdentifierName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get the unique identifier for the user session.
     *
     * @return mixed
     */
    public function getIdentifier(): mixed
    {
        return $this->{$this->getIdentifierName()};
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getUserIdentifierName(): string
    {
        return $this->user()->getForeignKeyName();
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getUserIdentifier(): mixed
    {
        return $this->{$this->getUserIdentifierName()};
    }

    /**
     * Determine whether this session has a "remember me" token.
     *
     * @return bool
     */
    public function isRemembered(): bool
    {
        return $this->remember_token !== null;
    }
}
