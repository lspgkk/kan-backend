<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia1Feedback extends Model
{
    protected $table = 'ia1_feedbacks';
    
    protected $fillable = [
        'id_izin',
        'id_kelompok',
        'umpan_balik'
    ];
}
