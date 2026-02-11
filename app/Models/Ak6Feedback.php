<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak6Feedback extends Model
{
    protected $table = 'ak6_feedbacks';
    
    protected $fillable = [
        'id_izin',
        'rekomendasi1',
        'rekomendasi2',
        'catatan_asesor1',
        'catatan_asesor2',
    ];
}
