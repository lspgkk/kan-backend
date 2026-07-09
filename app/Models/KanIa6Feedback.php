<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa6Feedback extends Model
{
    protected $table = 'kan_ia6_feedbacks';

    protected $fillable = [
        'id_izin',
        'kan_praktisi_jabatan_id',
        'umpan_balik',
        'unit_elemen_kuk',
    ];

    public function kanPraktisiJabatan()
    {
        return $this->belongsTo(KanPraktisiJabatan::class, 'kan_praktisi_jabatan_id', 'id');
    }
}
