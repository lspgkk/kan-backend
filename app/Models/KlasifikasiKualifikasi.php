<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KlasifikasiKualifikasi extends Model
{
    use HasFactory;

    protected $fillable = ([
        'id_izin',
        'id',
        'id_klasifikasi_kualifikasi',
        'updated',
        'created',
        'creator',
        'data_id',
        'lsp',
        'subklasifikasi',
        'kualifikasi',
        'jabatan_kerja',
        'jenjang',
        'asosiasi',
        'kta',
        'tuk',
        'jenis_permohonan',
        'berita_acara_vv',
        'surat_permohonan',
        'surat_pengantar_pemohonan_asosiasi',
        'sertifikat_skk',
        'self_asesmen_apl',
        'sertifikat_skk',
        // 'no_registrasi_asosiasi',
        'klasifikasi',
        'metode_uji',
        'created_at',
        'balai',
        'kegiatan',
        // 'deleted_at'
    ]);

    public function personal()
    {
        return $this->belongsTo(
            Personal::class,
            'id_izin',
            'id_izin'
        );
    }

    public function tuk()
    {
        return $this->belongsTo(
            MyTuk::class,
            'tuk', // FK in klasifikasi_kualifikasi
            'id'   // PK in mytuk
        );
    }

    public function asosiasiRel()
    {
        return $this->belongsTo(Asosiasi::class, 'asosiasi', 'id');
    }

    public function asesiJadwal()
    {
        return $this->hasOne(AsesiJadwal::class, 'id_izin', 'id_izin');
    }

    protected $casts = [
        'lsp' => 'integer'
    ];
}
