<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia1Answer extends Model
{
    protected $fillable = [
        'id_izin',
        'dokumen_id',
        'soal_id',
        'penilaian_lanjut',
        'pencapaian'
    ];

    protected $casts = [
        'pencapaian' => 'boolean'
    ];
}
