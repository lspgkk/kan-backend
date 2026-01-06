<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'id_propinsi_bps',
        'nama_kabupaten_bps',
        'kode_kabupaten_bps',
        'id_propinsi_dagri',
        'nama_kabupaten_dagri',
        'kode_kabupaten_dagri',
        'cek',
        'created_by'
    ];
}
