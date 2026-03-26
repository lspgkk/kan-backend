<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TukDiscount extends Model
{
    protected $fillable = [
        'nama_tuk',
        'id_tuk_lpjk',
        'kategori'
    ];
}
