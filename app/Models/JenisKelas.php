<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisKelas extends Model
{
    protected $table = 'jenis_kelas';

    protected $fillable = [
        'nama_kelas',
        'status'
    ];
}
