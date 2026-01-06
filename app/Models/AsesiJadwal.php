<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsesiJadwal extends Model
{
    protected $fillable = [
        'jadwal_id',
        'id_izin',
        'account',
        'password',
        'kompeten'
    ];
}
