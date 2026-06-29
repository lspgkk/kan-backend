<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa5Answer extends Model
{
    protected $table = 'kan_ia5_answers';

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
