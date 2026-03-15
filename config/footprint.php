<?php

use ProAI\Footprint\UserSession;

return [

    /*
    |--------------------------------------------------------------------------
    | User session model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that is used to log a session "footprint". Whenever
    | a new "footprint" of the session should be logged, the model will be
    | used to store data like ip address and user agent in the database.
    |
    */

    'session_model' => UserSession::class,

    /*
    |--------------------------------------------------------------------------
    | Remember token duration
    |--------------------------------------------------------------------------
    |
    | The number of minutes a "remember me" token will remain valid. After
    | this duration, users are not any longer remembered and need to log
    | in again to obtain a new remember token for persistent sessions.
    |
    */

    'remember_duration' => 43200, // 30 days

    /*
    |--------------------------------------------------------------------------
    | Rotate remember token on login
    |--------------------------------------------------------------------------
    |
    | Determines whether the "remember me" token should be regenerated each
    | time the user is logged in via a remember token. Enabling this will
    | improve security by revoking a token, so it cannot be used again.
    |
    */

    'rotate_on_login' => true,

    /*
    |--------------------------------------------------------------------------
    | Logout all devices on default logout
    |--------------------------------------------------------------------------
    |
    | Determines whether the logout method should logout all devices or only
    | the current device. This option can be very helpful if using another
    | package like Laravel Fortify and you don't want to overwrite code.
    |
    */

    'logout_all_devices' => true,

    /*
    |--------------------------------------------------------------------------
    | Refresh interval of user sessions
    |--------------------------------------------------------------------------
    |
    | The number of minutes until the last used at timestamp of user sessions
    | will be refreshed in the database again. If the value is set to null,
    | the user sessions will be updated in the database on every request.
    |
    */

    'refresh_interval' => 5,

    /*
    |--------------------------------------------------------------------------
    | Expired user session retention
    |--------------------------------------------------------------------------
    |
    | The number of minutes expired records in the user sessions table are
    | kept in the database before these are eligible for deletion by the
    | cleanup process when these were not manually deleted previously.
    |
    */

    'expired_session_retention' => 1440, // 24 hours

];
