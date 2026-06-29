<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa5Feedback extends Model
{
    protected $table = 'kan_ia5_feedbacks';

    protected $fillable = [
        'id_izin',
        'umpan_balik',
    ];
}
