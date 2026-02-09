<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak6Answer extends Model
{
    protected $fillable = [
        'id_izin',
        'id_aspek',
        'validitas',
        'reliabel',
        'fleksibel',
        'adil'
    ];

    protected $casts = [
        'validitas' => 'boolean',
        'reliabel' => 'boolean',
        'fleksibel' => 'boolean',
        'adil' => 'boolean',
    ];
}
