<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak1Answer extends Model
{
    protected $table = 'ak1_answers';

    protected $fillable = [
        'id_izin',
        'id_referensi',
        'jawaban',
    ];

    protected $casts = [
        'jawaban' => 'boolean',
    ];

    public function referensi()
    {
        return $this->belongsTo(Referensi::class, 'id_referensi');
    }
}
