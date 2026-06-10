<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaKomtek extends Model
{
    protected $fillable = [
        'jadwal_id',
        'no_surat',
        'link',
        'has_ttd_komtek1',
        'has_ttd_komtek2',
        'has_ttd_komtek3',
        'inputby_id',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'has_ttd_komtek1' => 'boolean',
        'has_ttd_komtek2' => 'boolean',
        'has_ttd_komtek3' => 'boolean',
    ];
}
