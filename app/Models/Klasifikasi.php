<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Klasifikasi extends Model
{
    protected $table = 'klasifikasis';

    protected $fillable = [
        'id_klasifikasi',
        'klasifikasi',
        'klasifikasi_en',
    ];
}
