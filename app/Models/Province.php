<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $fillable = [
        'id_provinsi',
        'id_provinsi_dagri',
        'nama'
    ];
}
