<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    protected $fillable = ([
        'request_hash',
        'jadwal_id',
        'tuk_id',
        'admin_id',
        'asesor_id',
        'asesor2_id',
        'jenis_mohon',
        'keterangan',
        'jenjang_id',
        'komtek1',
        'komtek2',
        'komtek3',
        'ketua_lsp',
        'mgr_sertif',
        'tanggal_uji',
        'sesi',
        'link_meeting',
        'link_video',
        'pengajuan_tuk_ref',
        'file_verifikasi_tuk',
        'jenis_kelas',
        'jenis_anggaran',
        'tanggal_selesai',
        'subklasifikasi',
        'kualifikasi',
        'nama_kegiatan',
        'status',
        'is_started_praasesmen',
        'is_started',
        'tahap',
        'started_at',
        'sk_komtek_direktur_approved',
        'sk_pelaksanaan_uji_direktur_approved',
        'spt_asesor_direktur_approved',
        'spt_komtek_direktur_approved',
    ]);

    protected $casts = [
        'tuk_id' => 'integer',
        'admin_id' => 'integer',
        'asesor_id' => 'integer',
        'asesor2_id' => 'integer',
        'jenjang_id' => 'integer',
        'komtek1' => 'integer',
        'komtek2' => 'integer',
        'komtek3' => 'integer',
        'ketua_lsp' => 'integer',
        'mgr_sertif' => 'integer',
        'sesi' => 'integer',
        'tahap' => 'integer',
        'jenis_mohon' => 'integer',
        'is_started_praasesmen' => 'boolean',
        'is_started' => 'boolean',
        'sk_komtek_direktur_approved' => 'boolean',
        'sk_pelaksanaan_uji_direktur_approved' => 'boolean',
        'spt_asesor_direktur_approved' => 'boolean',
        'spt_komtek_direktur_approved' => 'boolean',
    ];

    /* ================= RELATIONS ================= */

    public function tuk()
    {
        return $this->belongsTo(MyTukBnsp::class, 'tuk_id', 'id');
    }

    public function pengajuanTuk()
    {
        return $this->belongsTo(PengajuanTuk::class, 'pengajuan_tuk_ref', 'ref_number');
    }

    /**
     * Resolve the TUK name, using PengajuanTuk.data_tuk.namaTuk
     * for online/daring classes when available.
     *
     * @param int|null $jenisKelasId The jenis_kelas_id from personal
     */
    public function resolveNamaTuk(?int $jenisKelasId = null): ?string
    {
        $shouldUsePengajuan = ($jenisKelasId === null)
            ? !empty($this->pengajuan_tuk_ref)
            : ($jenisKelasId === 3 && !empty($this->pengajuan_tuk_ref));

        if ($shouldUsePengajuan) {
            $pengajuan = $this->relationLoaded('pengajuanTuk')
                ? $this->pengajuanTuk
                : $this->pengajuanTuk()->first();

            if ($pengajuan && !empty($pengajuan->data_tuk['namaTuk'])) {
                return $pengajuan->data_tuk['namaTuk'];
            }
        }

        return $this->tuk?->nama;
    }

    /**
     * Resolve the TUK address, using PengajuanTuk.data_tuk.alamat
     * for online/daring classes when available.
     *
     * @param int|null $jenisKelasId The jenis_kelas_id from personal
     */
    public function resolveAlamatTuk(?int $jenisKelasId = null): ?string
    {
        $shouldUsePengajuan = ($jenisKelasId === null)
            ? !empty($this->pengajuan_tuk_ref)
            : ($jenisKelasId === 3 && !empty($this->pengajuan_tuk_ref));

        if ($shouldUsePengajuan) {
            $pengajuan = $this->relationLoaded('pengajuanTuk')
                ? $this->pengajuanTuk
                : $this->pengajuanTuk()->first();

            if ($pengajuan && !empty($pengajuan->data_tuk['alamat'])) {
                return $pengajuan->data_tuk['alamat'];
            }
        }

        return $this->tuk?->alamat;
    }

    public function asesor()
    {
        return $this->belongsTo(AsesorBnsp::class, 'asesor_id', 'id');
    }

    public function asesor2()
    {
        return $this->belongsTo(AsesorBnsp::class, 'asesor2_id', 'id');
    }

    public function komtekRecommendations()
    {
        return $this->hasMany(
            KomtekRecommendation::class,
            'id_izin',
            'id_izin'
        );
    }

    public function asesiJadwals()
    {
        return $this->hasMany(
            AsesiJadwal::class,
            'jadwal_id',
            'jadwal_id'
        );
    }

    public function sptAsesor()
    {
        return $this->hasOne(
            SPTAsesor::class,
            'jadwal_id',
            'jadwal_id'
        );
    }

    public function sptKomtek()
    {
        return $this->hasOne(
            SPTKomtek::class,
            'jadwal_id',
            'jadwal_id'
        );
    }
}
