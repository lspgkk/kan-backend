<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JenisKegiatan extends Model
{
    use HasUuids;

    protected $table = 'jenis_kegiatans';
    protected $fillable = ['name', 'kode', 'deskripsi'];
}
