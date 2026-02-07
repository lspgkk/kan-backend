<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak07Answer extends Model
{
    protected $table = 'ak07_answers';

    protected $fillable = [
        'id_izin',
        'referensi_id',
        'kelompok_id',
        'answer_bool',
        'answer_text',
    ];

    protected $casts = [
        'answer_bool' => 'boolean',
    ];

    public function referensi()
    {
        return $this->belongsTo(Referensi::class);
    }

    public function kelompok()
    {
        return $this->belongsTo(Kelompok::class);
    }
}
