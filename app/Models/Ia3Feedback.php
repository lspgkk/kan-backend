<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia3Feedback extends Model
{
    protected $table = 'ia3_feedbacks';
    
    protected $fillable = [
        'id_izin',
        'umpan_balik'
    ];
}
