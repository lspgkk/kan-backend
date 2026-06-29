<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa4bAnswer extends Model
{
    protected $table = 'kan_ia4b_answers';

    protected $fillable = [
        'id_izin',
        'dokumen_id',
        'soal_id',
        'jawaban',
        'skor',
        'is_rekomendasi',
        'rekomendasi',
    ];

    protected $casts = [
        'skor' => 'integer',
        'is_rekomendasi' => 'boolean',
        'rekomendasi' => 'boolean',
    ];
}
