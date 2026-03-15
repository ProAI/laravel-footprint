<?php

namespace ProAI\Footprint\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ProAI\Footprint\Contracts\HasSessions as HasSessionsContract;
use ProAI\Footprint\HasSessions;

class User extends Authenticatable implements HasSessionsContract
{
    use HasSessions;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];
}
