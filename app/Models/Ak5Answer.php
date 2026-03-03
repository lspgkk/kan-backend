<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ak5Answer extends Model
{
    protected $fillable = [
        'id_izin',
        'keterangan',
        'aspek',
        'pencatatan_penolakan',
        'saran',
        'catatan'
    ];
}
