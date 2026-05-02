<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ia08Recommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_izin',
        'is_kompeten',
        'bukti_tambahan',
        'rekomendasi_unit',
        'rekomendasi_elemen',
        'rekomendasi_kuk',
    ];

    protected $casts = [
        'is_kompeten' => 'boolean',
    ];
}