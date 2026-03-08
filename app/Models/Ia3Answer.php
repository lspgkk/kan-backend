<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia3Answer extends Model
{
    protected $fillable = [
        'id_izin',
        'dokumen_id',
        'soal_id',
        'tanggapan',
        'pencapaian'
    ];

    protected $casts = [
        'pencapaian' => 'boolean'
    ];
}
