<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia4bAnswer extends Model
{
    protected $table = 'ia4b_answers';

    protected $fillable = [
        'id_izin',
        'dokumen_id',
        'soal_id',
        'jawaban',
        'pencapaian',
        'is_rekomendasi',
        'rekomendasi',
    ];

    protected $casts = [
        'pencapaian' => 'boolean',
        'is_rekomendasi' => 'boolean',
        'rekomendasi' => 'boolean',
    ];
}

