<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Apl1Answer extends Model
{
    protected $table = 'apl1_answers';

    protected $fillable = [
        'id_izin',
        'perusahaan',
        'jabatan',
        'alamat_kantor',
        'kode_pos',
        'telepon_kantor',
        'fax',
        'email_kantor',
        'is_diterima',
    ];

    public $timestamps = true;

    protected $casts = [
        'is_diterima' => 'boolean',
    ];
}
