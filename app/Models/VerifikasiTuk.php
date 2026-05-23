<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerifikasiTuk extends Model
{
    protected $table = 'verifikasi_tuk';

    protected $fillable = [
        'jadwal_id',
        'id_izin',
        'no_surat_tugas',
        'no_surat_ba',
        'no_surat_sk',
        'no_surat_peminjaman',
        'nama_tuk',
        'alamat',
        'hari_tanggal',
        'metode_verifikasi',
        'jumlah_peserta',
        'skema_sertifikasi',
        'jenjang',
        'ruangan_uji_images',
        'laptop_images',
        'kecepatan_jaringan_images',
        'kondisi_ruangan',
        'keterangan_ruangan',
        'kondisi_laptop',
        'keterangan_laptop',
        'kondisi_jaringan',
        'keterangan_jaringan',
        'rekomendasi',
        'verifikator_id',
        'verifikator_qr',
        'ketua_tuk_id',
        'ketua_tuk_qr',
        'direktur_id',
        'direktur_qr',
        'has_ttd_sk',
        'direktur_sk_qr',
        'status',
        'pdf_link',
    ];

    protected $casts = [
        'ruangan_uji_images' => 'array',
        'laptop_images' => 'array',
        'kecepatan_jaringan_images' => 'array',
        'hari_tanggal' => 'date',
        'has_ttd_sk' => 'boolean',
    ];

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_id', 'jadwal_id');
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'id_izin', 'id_izin');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    public function ketuaTuk()
    {
        return $this->belongsTo(User::class, 'ketua_tuk_id');
    }

    public function direktur()
    {
        return $this->belongsTo(User::class, 'direktur_id');
    }
}
