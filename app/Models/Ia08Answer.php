<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ia08Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_izin',
        'soal_id',
        'valid',
        'asli',
        'terkini',
        'memadai',
    ];

    protected $casts = [
        'valid' => 'boolean',
        'asli' => 'boolean',
        'terkini' => 'boolean',
        'memadai' => 'boolean',
    ];

    public function soal()
    {
        return $this->belongsTo(Soal::class, 'soal_id');
    }
}