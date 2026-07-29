<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'scopes',
        'ip_whitelist',
        'rate_limit',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'scopes'       => 'array',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];
}