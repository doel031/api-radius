<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    protected $fillable = [
        'api_key',
        'endpoint',
        'method',
        'ip_address',
        'response_status',
    ];
}