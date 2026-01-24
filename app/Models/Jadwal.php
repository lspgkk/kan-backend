<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
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
        'status',
        'is_started',
        'started_at'
    ]);

    /* ================= RELATIONS ================= */

    public function tuk()
    {
        return $this->belongsTo(MyTukBnsp::class, 'tuk_id', 'id');
    }

    public function asesor()
    {
        return $this->belongsTo(AsesorBnsp::class, 'asesor_id', 'id');
    }

    public function asesor2()
    {
        return $this->belongsTo(AsesorBnsp::class, 'asesor2_id', 'id');
    }

    public function skema()
    {
        return $this->belongsTo(Skema::class, 'skema_id', 'id');
    }
}
