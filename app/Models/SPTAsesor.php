<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SPTAsesor extends Model
{
    protected $table = 's_p_t_asesors';

    protected $fillable = [
        'jadwal_id',
        'spt_no',
        'link',
        'asesor_id',
    ];
}
