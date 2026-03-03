<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomtekRecommendation extends Model
{
    protected $fillable = [
        'id_izin',
        'id_komtek',
        'rekomendasi'
    ];
}
