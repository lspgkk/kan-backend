<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia5Feedback extends Model
{
    protected $table = 'ia5_feedbacks';
    
    protected $fillable = [
        'id_izin',
        'umpan_balik'
    ];
}
