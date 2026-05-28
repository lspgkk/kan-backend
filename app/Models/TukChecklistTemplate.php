<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TukChecklistTemplate extends Model
{
    protected $fillable = [
        'kategori',
        'nama',
        'spesifikasi',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
