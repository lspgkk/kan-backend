<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

    protected $fillable = ([
        'id_izin',
        'jadwal_id',
        'tuk_id',
        'asesor_id',
        'asesor2_id',
        'is_fg',
        'jenis_mohon',
        'skema_id',
        'keterangan',
        'jenjang_id',
        'komtek1',
        'komtek2',
        'komtek3',
        'ketua_lsp',
        'mgr_sertif',
        'tanggal_uji',
        'nama_kegiatan',
        'status'
    ]);
}
