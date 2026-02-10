<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak2Feedback extends Model
{
    protected $table = 'ak2_feedbacks';
    
    protected $fillable = [
        'id_izin',
        'is_kompeten',
        'tindak_lanjut',
        'komentar',
    ];

    protected $casts = [
        'is_kompeten' => 'boolean'
    ];
}