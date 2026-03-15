<?php

namespace ProAI\Footprint;

use Illuminate\Database\Eloquent\Model;
use ProAI\Footprint\Contracts\Sessionable as SessionableContract;

class UserSession extends Model implements SessionableContract
{
    use Sessionable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'remember_token',
        'remember_issued_at',
        'ip_address',
        'user_agent',
        'last_used_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'remember_issued_at' => 'datetime',
        ];
    }
}
