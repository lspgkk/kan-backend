<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SPTKomtek extends Model
{
    protected $table = 's_p_t_komteks';

    protected $fillable = [
        'jadwal_id',
        'no_surat',
        'link',
    ];
}
