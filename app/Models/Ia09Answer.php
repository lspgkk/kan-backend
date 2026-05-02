<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ia09Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_izin',
        'soal_id',
        'kesimpulan',
        'is_kompeten',
    ];

    protected $casts = [
        'is_kompeten' => 'boolean',
    ];

    public function soal()
    {
        return $this->belongsTo(Soal::class, 'soal_id');
    }
}