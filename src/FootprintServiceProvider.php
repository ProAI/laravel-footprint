<?php

namespace ProAI\Footprint;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use ProAI\Footprint\Console\Commands\PruneExpired;
use ProAI\Footprint\Contracts\UserSessionRepository as UserSessionRepositoryContract;

class FootprintServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        config([
            'auth.guards.footprint' => array_merge([
                'driver' => 'footprint',
                'provider' => null,
            ], config('auth.guards.footprint', [])),
        ]);

        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(__DIR__.'/../config/footprint.php', 'footprint');
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configurePublishing();
        $this->configureSessionRepository();
        $this->configureGuard();
    }

    /**
     * Configure the publishable resources offered by the package.
     *
     * @return void
     */
    protected function configurePublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'footprint-migrations');

            $this->publishes([
                __DIR__.'/../config/footprint.php' => config_path('footprint.php'),
            ], 'footprint-config');

            $this->commands([
                PruneExpired::class,
            ]);
        }
    }

    /**
     * Configure the footprint user session repository.
     *
     * @return void
     */
    protected function configureSessionRepository(): void
    {
        $this->app->singleton(UserSessionRepositoryContract::class, function (Application $app) {
            /** @var int $lifetime */
            $lifetime = $app['config']->get('session.lifetime', 120);

            /** @var int $rememberDuration */
            $rememberDuration = $app['config']->get('footprint.remember_duration', 43200);

            /** @var int|null $refreshInterval */
            $refreshInterval = $app['config']->get('footprint.refresh_interval', 5);

            return new UserSessionRepository(
                $app,
                $app['config']->get('footprint.session_model'),
                lifetime: $lifetime,
                rememberDuration: $rememberDuration,
                refreshInterval: $refreshInterval,
            );
        });
    }

    /**
     * Configure the footprint authentication guard.
     *
     * @return void
     */
    protected function configureGuard(): void
    {
        Auth::resolved(function (Factory $auth) {
            $auth->extend('footprint', function (Application $app, string $name, array $config) {
                return $this->createGuard($app, $name, $config);
            });
        });
    }

    /**
     * Register the guard.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @param  string  $name
     * @param  array<string, mixed>  $config
     * @return \ProAI\Footprint\FootprintGuard
     */
    protected function createGuard(Application $app, string $name, array $config): FootprintGuard
    {
        /** @var bool $rehashOnLogin */
        $rehashOnLogin = $app['config']->get('hashing.rehash_on_login', true);

        /** @var bool $rotateOnLogin */
        $rotateOnLogin = $app['config']->get('footprint.rotate_on_login', true);

        /** @var bool $logoutAllDevices */
        $logoutAllDevices = $app['config']->get('footprint.logout_all_devices', true);

        /** @var int $timeboxDuration */
        $timeboxDuration = $app['config']->get('auth.timebox_duration', 200000);

        /** @var int $rememberDuration */
        $rememberDuration = $app['config']->get('footprint.remember_duration', 43200);

        /** @var \Illuminate\Contracts\Auth\UserProvider $provider */
        $provider = Auth::createUserProvider($config['provider'] ?? null);

        $guard = new FootprintGuard(
            $name,
            $provider,
            $app->make(UserSessionRepositoryContract::class),
            $app['session.store'],
            rehashOnLogin: $rehashOnLogin,
            rotateOnLogin: $rotateOnLogin,
            logoutAllDevices: $logoutAllDevices,
            timeboxDuration: $timeboxDuration,
        );

        $guard->setCookieJar($app['cookie']);

        $guard->setDispatcher($app['events']);

        $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

        $guard->setRememberDuration($rememberDuration);

        return $guard;
    }
}
