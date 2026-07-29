<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Radusergroup extends Model
{
    protected $table = 'radusergroup';
    public $timestamps = true;
    protected $fillable = ['username', 'groupname', 'priority', 'created_at', 'updated_at'];
}