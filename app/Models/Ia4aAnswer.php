<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia4aAnswer extends Model
{
    protected $table = 'ia4a_answers';

    protected $fillable = [
        'id_izin',
        'soal_id',
        'jawaban',
    ];

    /* ================= RELATIONS ================= */

    public function soal()
    {
        return $this->belongsTo(Soal::class, 'soal_id');
    }
}
