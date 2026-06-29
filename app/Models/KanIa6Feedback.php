<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanIa6Feedback extends Model
{
    protected $table = 'kan_ia6_feedbacks';

    protected $fillable = [
        'id_izin',
        'umpan_balik',
        'unit_elemen_kuk',
    ];
}
