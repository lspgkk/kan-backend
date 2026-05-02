<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ia10Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_izin',
        'referensi_id',
        'answer',
        'essay_answer',
    ];

    protected $casts = [
        'answer' => 'boolean',
    ];

    public function referensi()
    {
        return $this->belongsTo(Referensi::class, 'referensi_id');
    }
}