<?php

namespace ProAI\Footprint;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ProAI\Footprint\Contracts\Sessionable as SessionableContract;
use ProAI\Footprint\Contracts\UserSessionRepository as UserSessionRepositoryContract;

class UserSessionRepository implements UserSessionRepositoryContract
{
    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected Container $container;

    /**
     * The Eloquent user session model.
     *
     * @var class-string<\ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model>
     */
    protected string $model;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected int $lifetime;

    /**
     * The number of minutes that the "remember me" cookie should be valid for.
     *
     * @var int
     */
    protected int $rememberDuration;

    /**
     * The number of minutes that the session should be updated.
     *
     * @var int|null
     */
    protected ?int $refreshInterval;

    /**
     * Create a new database user session provider.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @param  class-string<\ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model>  $model
     * @param  int  $lifetime
     * @param  int  $rememberDuration
     * @param  int|null  $refreshInterval
     */
    public function __construct(Container $container, string $model, int $lifetime, int $rememberDuration, ?int $refreshInterval)
    {
        $this->container = $container;
        $this->model = $model;
        $this->lifetime = $lifetime;
        $this->rememberDuration = $rememberDuration;
        $this->refreshInterval = $refreshInterval;
    }

    /**
     * Create a user session.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions  $user
     * @param  string|null  $token
     * @return \ProAI\Footprint\Contracts\Sessionable
     */
    public function create(UserContract $user, #[\SensitiveParameter] ?string $token): SessionableContract
    {
        $session = $this->createModel();

        $time = now();

        $session->forceFill($this->getRequestData() + [
            $session->getUserIdentifierName() => $user->getAuthIdentifier(),
            'remember_token' => $this->hashRememberToken($token),
            'remember_issued_at' => $time,
            'last_used_at' => $time,
        ])->save();

        return $session;
    }

    /**
     * Retrieve a user session by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \ProAI\Footprint\Contracts\Sessionable|null
     */
    public function getById(mixed $identifier): ?SessionableContract
    {
        $model = $this->createModel();

        /** @var (\ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model)|null */
        return $this->newModelQuery($model)
            ->where($model->getIdentifierName(), $identifier)
            ->whereNot(function (Builder $q) {
                $this->whereExpired($q);
            })
            ->first();
    }

    /**
     * Retrieve a user session by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \ProAI\Footprint\Contracts\Sessionable|null
     */
    public function getByToken(mixed $identifier, #[\SensitiveParameter] string $token): ?SessionableContract
    {
        $retrievedModel = $this->getById($identifier);

        if (! $retrievedModel) {
            return null;
        }

        /** @var string $hashedToken */
        $hashedToken = $retrievedModel->remember_token;

        /** @var string $hashedInput */
        $hashedInput = $this->hashRememberToken($token);

        return hash_equals($hashedToken, $hashedInput) ? $retrievedModel : null;
    }

    /**
     * Update session activity (e.g. last_used_at).
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model  $session
     * @return void
     */
    public function updateActivity(SessionableContract $session): void
    {
        $session->forceFill($this->getRequestData() + [
            'last_used_at' => now(),
        ])->save();
    }

    /**
     * Update the "remember me" token for the given user session in storage.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model  $session
     * @param  string|null  $token
     * @return void
     */
    public function updateRememberToken(SessionableContract $session, #[\SensitiveParameter] ?string $token): void
    {
        $time = now();

        $session->forceFill($this->getRequestData() + [
            'remember_token' => $this->hashRememberToken($token),
            'remember_issued_at' => $time,
            'last_used_at' => $time,
        ])->save();
    }

    /**
     * Hash the "remember me" token.
     *
     * @param  string|null  $token
     * @return string|null
     */
    protected function hashRememberToken(?string $token): ?string
    {
        if (! $token) {
            return null;
        }

        return hash('sha256', $token);
    }

    /**
     * Delete a user session.
     *
     * @param  \ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model  $session
     * @return void
     */
    public function delete(SessionableContract $session): void
    {
        $session->delete();
    }

    /**
     * Delete all sessions for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable&\ProAI\Footprint\Contracts\HasSessions  $user
     * @param  bool  $exceptCurrent
     * @return void
     */
    public function deleteAllFor(UserContract $user, bool $exceptCurrent = false): void
    {
        $model = $this->createModel();

        $query = $this->newModelQuery($model)
            ->where($model->getUserIdentifierName(), $user->getAuthIdentifier());

        if ($exceptCurrent && $currentSession = $user->currentSession()) {
            $query->where($model->getIdentifierName(), '!=', $currentSession->getIdentifier());
        }

        $query->delete();
    }

    /**
     * Delete all expired sessions.
     *
     * @param  int|null  $retentionPeriod
     * @return void
     */
    public function deleteExpired(?int $retentionPeriod = null): void
    {
        $query = $this->newModelQuery();

        $this->whereExpired($query, $retentionPeriod);

        $query->delete();
    }

    /**
     * Apply expired where conditions to given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  int|null  $retentionPeriod
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function whereExpired(Builder $query, ?int $retentionPeriod = null): Builder
    {
        $time = now()->subMinutes((int) $retentionPeriod);

        // Expired remember token
        $query->where(function (Builder $q) use ($time) {
            $q->whereNull('remember_token');
            $q->orWhere('remember_issued_at', '<=', $time->copy()->subMinutes($this->rememberDuration));
        });

        $timeout = $this->lifetime + ($this->refreshInterval ?? 0);

        // Expired session
        $query->where('last_used_at', '<=', $time->copy()->subMinutes($timeout));

        return $query;
    }

    /**
     * Get the user session request data.
     *
     * @return array{ip_address: string|null, user_agent: string}|array{}
     */
    protected function getRequestData(): array
    {
        if (! $this->container->bound('request')) {
            return [];
        }

        return [
            'ip_address' => $this->ipAddress(),
            'user_agent' => $this->userAgent(),
        ];
    }

    /**
     * Get the IP address for the current request.
     *
     * @return string|null
     */
    protected function ipAddress(): ?string
    {
        return $this->container->make('request')->ip();
    }

    /**
     * Get the user agent for the current request.
     *
     * @return string
     */
    protected function userAgent(): string
    {
        return substr(mb_convert_encoding((string) $this->container->make('request')->header('User-Agent'), 'UTF-8'), 0, 500);
    }

    /**
     * Get a new query builder for the model instance.
     *
     * @param  (\ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model)|null  $model
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function newModelQuery(?Model $model = null): Builder
    {
        return is_null($model)
            ? $this->createModel()->newQuery()
            : $model->newQuery();
    }

    /**
     * Create a new instance of the model.
     *
     * @return \ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model
     */
    public function createModel(): Model
    {
        $class = '\\'.ltrim($this->model, '\\');

        /** @var \ProAI\Footprint\Contracts\Sessionable&\Illuminate\Database\Eloquent\Model */
        return new $class;
    }
}
