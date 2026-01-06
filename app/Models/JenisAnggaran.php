<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisAnggaran extends Model
{
    protected $table = 'jenis_anggaran';

    protected $fillable = [
        'nama_anggaran',
        'status'
    ];
}
