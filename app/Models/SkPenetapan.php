<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkPenetapan extends Model
{
    protected $fillable = [
        'jadwal_id',
        'no_surat',
        'link',
        'has_ttd_direktur',
        'inputby_id',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'has_ttd_direktur' => 'boolean',
    ];
}
