<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Radacct extends Model
{
    protected $table = 'radacct';
    
    // Memberitahu Laravel bahwa Primary Key-nya bernama 'radacctid'
    protected $primaryKey = 'radacctid'; 

    // Mematikan pencarian otomatis kolom created_at & updated_at
    public $timestamps = false; 
}