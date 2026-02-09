<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak4Reason extends Model
{
    protected $table = 'ak4_reasons';

    protected $fillable = [
        'id_izin',
        'alasan',
    ];
}
