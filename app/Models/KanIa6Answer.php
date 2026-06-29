<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa6Answer extends Model
{
    protected $table = 'kan_ia6_answers';

    protected $fillable = [
        'id_izin',
        'dokumen_id',
        'soal_id',
        'jawaban',
        'skor',
    ];

    protected $casts = [
        'skor' => 'integer',
    ];
}
