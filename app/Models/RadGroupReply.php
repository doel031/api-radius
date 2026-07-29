<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RadGroupReply extends Model
{
    protected $table = 'radgroupreply';
    public $timestamps = true; // Tabel bawaan FreeRADIUS biasanya tidak menggunakan timestamps
    protected $fillable = [
        'groupname', 
        'attribute', 
        'op', 
        'value', 
        'created_at', 
        'updated_at'
    ];
}