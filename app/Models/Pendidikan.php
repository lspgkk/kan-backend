<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pendidikan extends Model
{
    use HasFactory;

    protected $table = 'pendidikans';

    protected $fillable = [
        'id_izin',
        'id',
        'id_pendidikan',
        'updated',
        'created',
        'creator',
        'data_id',
        'nama_sekolah_perguruan_tinggi',
        'program_studi',
        'no_ijazah',
        'tahun_lulus',
        'tanggal_lulus',
        'jenjang',
        'alamat',
        'negara',
        'propinsi',
        'kabupaten',
        'scan_ijazah_legalisir',
        'scan_surat_keterangan',
        'sertifikat_pemberian_kompetensi_tambahan',
        'sertifikat_sibima'
    ];

    public function personals()
    {
        return $this->belongsTo(Personal::class, 'id_izin');
    }
}
