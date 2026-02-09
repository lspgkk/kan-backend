<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak3Answer extends Model
{
    protected $table = 'ak3_answers';

    protected $fillable = [
        'id_izin',
        'soal_id',
        'is_kompeten',
        'catatan',
    ];

    protected $casts = [
        'is_kompeten' => 'boolean',
    ];
}
