<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak2Answer extends Model
{
    protected $fillable = [
        'id_izin',
        'id_unit_kompetensi',
        'observasi',
        'portofolio',
        'pertanyaan_wawancara',
        'pertanyaan_lisan',
        'pertanyaan_tertulis',
        'proyek_kerja'
    ];

    protected $casts = [
        'observasi' => 'boolean',
        'portofolio' => 'boolean',
        'pertanyaan_wawancara' => 'boolean',
        'pertanyaan_lisan' => 'boolean',
        'pertanyaan_tertulis' => 'boolean',
        'proyek_kerja' => 'boolean',
    ];
}
