<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsenKomtek extends Model
{
    protected $fillable = [
        'jadwal_id',
        'no_surat',
        'link'
    ];
}
