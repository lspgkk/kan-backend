<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak4Answer extends Model
{
    protected $table = 'ak4_answers';

    protected $fillable = [
        'id_izin',
        'referensi_id',
        'kelompok_id',
        'jawaban',
    ];

    /* ================= RELATIONS ================= */

    public function referensi()
    {
        return $this->belongsTo(Referensi::class, 'referensi_id');
    }

    protected $casts = [
        'jawaban' => 'boolean',
    ];
}
