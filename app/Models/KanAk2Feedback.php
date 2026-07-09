<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanAk2Feedback extends Model
{
    protected $table = 'kan_ak2_feedbacks';

    protected $fillable = [
        'id_izin',
        'kan_praktisi_jabatan_id',
        'total_skor_dit',
        'total_skor_pg',
        'total_skor_esai',
        'nilai_skor_dit',
        'nilai_skor_pg',
        'nilai_skor_esai',
        'skor_nilai_akhir',
        'is_kompeten',
        'threshold_passing',
        'tindak_lanjut',
        'komentar',
    ];

    protected $casts = [
        'is_kompeten' => 'boolean',
    ];

    public function kanPraktisiJabatan()
    {
        return $this->belongsTo(KanPraktisiJabatan::class, 'kan_praktisi_jabatan_id', 'id');
    }
}
