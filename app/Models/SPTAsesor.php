<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SPTAsesor extends Model
{
    protected $fillable = [
        'jadwal_id',
        'spt_no',
        'link',
        'asesor_id',
    ];
}
