<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa5Feedback extends Model
{
    protected $table = 'kan_ia5_feedbacks';

    protected $fillable = [
        'id_izin',
        'kan_praktisi_jabatan_id',
        'umpan_balik',
    ];

    public function kanPraktisiJabatan()
    {
        return $this->belongsTo(KanPraktisiJabatan::class, 'kan_praktisi_jabatan_id', 'id');
    }
}
