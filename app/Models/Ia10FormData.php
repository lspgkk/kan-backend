<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ia10FormData extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_izin',
        'nama_pengawas',
        'tempat_kerja',
        'alamat',
        'telepon',
    ];
}