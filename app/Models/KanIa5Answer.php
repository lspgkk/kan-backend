<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa5Answer extends Model
{
    protected $table = 'kan_ia5_answers';

    protected $fillable = [
        'id_izin',
        'kan_praktisi_jabatan_id',
        'dokumen_id',
        'soal_id',
        'jawaban',
        'skor',
    ];

    protected $casts = [
        'skor' => 'integer',
    ];

    public function kanPraktisiJabatan()
    {
        return $this->belongsTo(KanPraktisiJabatan::class, 'kan_praktisi_jabatan_id', 'id');
    }
}
