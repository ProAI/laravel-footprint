# Laravel Footprint

Laravel Footprint is a session tracking package for Laravel. It extends Laravel's authentication guard to persist user sessions in the database, giving you full visibility and control over active sessions across devices.

## Why use this package?

Laravel's database session driver lets you query the `sessions` table to list active sessions and delete rows to log out devices. However, this approach breaks down with "remember me" functionality:

- **Global remember token.** Laravel stores a single `remember_token` on the `users` table, shared across all devices. Invalidating one device's remember cookie requires cycling the token, which logs out every remembered device.
- **Ghost devices.** When a session expires and gets garbage collected, the row is deleted, but the remember cookie remains valid. The device disappears from your session list yet can silently re-authenticate on the next request.
- **Password required.** Laravel's built-in `Auth::logoutOtherDevices()` requires the user's password because it rehashes it to invalidate other sessions. There is no way to log out other devices without it.

Laravel Footprint solves these problems by storing **per-device remember tokens** in a dedicated `user_sessions` table, decoupled from Laravel's session store:

- Each session has its own remember token with automatic rotation and expiration — revoking one device does not affect others.
- Session records persist independently of Laravel's session garbage collection, so expired sessions remain visible and manageable until explicitly revoked.
- Log out any device or all other devices without requiring the user's password.

On top of that, the package tracks all active sessions per user with IP address, user agent, and last activity — built on top of Laravel's session guard with a configurable refresh interval and an Artisan command for pruning expired sessions.

## Requirements

- PHP 8.0+
- Laravel 11 or 12

## Installation

```bash
composer require proai/laravel-footprint
```

Publish the configuration and migration files:

```bash
php artisan vendor:publish --provider="ProAI\Footprint\FootprintServiceProvider" --tag="footprint-config"
php artisan vendor:publish --provider="ProAI\Footprint\FootprintServiceProvider" --tag="footprint-migrations"
```

Run the migration:

```bash
php artisan migrate
```

## Setup

### User Model

Add the `HasSessions` contract and trait to your user model:

```php
use ProAI\Footprint\Contracts\HasSessions as HasSessionsContract;
use ProAI\Footprint\HasSessions;

class User extends Authenticatable implements HasSessionsContract
{
    use HasSessions;
}
```

### Authentication Guard

Set the guard driver to `footprint` in `config/auth.php`:

```php
'guards' => [
    'web' => [
        'driver' => 'footprint',
        'provider' => 'users',
    ],
],
```

### Middleware

Register the middleware in your application. `TrackSession` updates the session's last activity timestamp. `AuthenticateSession` validates session integrity and detects password changes.

```php
use ProAI\Footprint\Middleware\AuthenticateSession;
use ProAI\Footprint\Middleware\TrackSession;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        AuthenticateSession::class,
        TrackSession::class,
    ]);
})
```

## Usage

### Authentication

The footprint guard implements Laravel's `StatefulGuard` interface, so authentication works as usual:

```php
use Illuminate\Support\Facades\Auth;

Auth::attempt($credentials);
Auth::login($user);
Auth::logout();
```

### Session Management

Access the current session or list all sessions for a user:

```php
// Current session
$session = $user->currentSession();
$session->ip_address;
$session->user_agent;
$session->last_used_at;
$session->isRemembered();

// All sessions
foreach ($user->sessions as $session) {
    $session->ip_address;
    $session->user_agent;
    $session->last_used_at;
}
```

### Logging Out Devices

Log out a specific device:

```php
Auth::logoutDevice($session);
```

Log out all other devices except the current one. Optionally pass the user's password to rehash it:

```php
Auth::logoutOtherDevices();

// or with password rehashing
Auth::logoutOtherDevices($password);
```

### Pruning Expired Sessions

Remove expired sessions from the database:

```bash
php artisan footprint:prune-expired
```

You can schedule this command to run periodically:

```php
$schedule->command('footprint:prune-expired')->daily();
```

## Configuration

The published config file (`config/footprint.php`) provides the following options:

| Option | Default | Description |
|--------|---------|-------------|
| `session_model` | `UserSession::class` | Eloquent model used for session records |
| `remember_duration` | `43200` (30 days) | How long a remember me token stays valid (in minutes) |
| `rotate_on_login` | `true` | Regenerate remember token each time it is used |
| `logout_all_devices` | `true` | Whether `logout()` logs out all devices or only the current one |
| `refresh_interval` | `5` | Minutes between session activity updates |
| `expired_session_retention` | `1440` (24 hours) | How long expired sessions are kept before pruning |

## Custom Session Model

You can extend the default `UserSession` model by implementing the `Sessionable` contract:

```php
use ProAI\Footprint\Contracts\Sessionable as SessionableContract;
use ProAI\Footprint\Sessionable;
use Illuminate\Database\Eloquent\Model;

class CustomSession extends Model implements SessionableContract
{
    use Sessionable;

    protected $table = 'user_sessions';

    protected $fillable = [
        'user_id',
        'remember_token',
        'remember_issued_at',
        'ip_address',
        'user_agent',
        'last_used_at',
    ];
}
```

Then update the config:

```php
'session_model' => CustomSession::class,
```

## Testing

```bash
composer test
```

## License

This package is released under the [MIT License](LICENSE).
