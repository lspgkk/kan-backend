<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    use HasFactory;

    protected $table = 'personals';
    protected $primaryKey = 'id_izin';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id_izin',
        'id_pemohon',
        'type',
        'updated',
        'created',
        'creator',
        'data_id',
        'nik',
        'nama',
        'tempat_lahir',
        'tanggal_lahir',
        'email',
        'telepon',
        'npwp',
        'status',
        'jenis_kelamin',
        'alamat',
        'negara',
        'propinsi',
        'kabupaten',
        'kodepos',
        'ktp',
        'surat_pernyataan_kebenaran_data',
        'file_npwp',
        'pas_foto',
        'inputby_id',
        'inputby_time',
        'verif_time',
        'verifby_id',
        'valid_time',
        'validby_id',
        'valid2_time',
        'valid2by_id',
        'is_deleted',
        'deletedby_id',
        'deleted_at',
        'rencana_tanggal_uji',
        'metode_uji',
        'jenis_kelas_id',
        'wa_uuid',
        'account',
        'password_plain',
        'catatan',
    ];

    public function pendidikans()
    {
        return $this->hasMany(Pendidikan::class, 'id_izin');
    }

    public function proyeks()
    {
        return $this->hasMany(Proyek::class, 'id_izin');
    }

    public function pelatihans()
    {
        return $this->hasMany(Pelatihan::class, 'id_izin');
    }
    public function sertifikat_sukets()
    {
        return $this->hasMany(SertifikatSuket::class, 'id_izin');
    }
    public function klasifikasi_kualifikasi()
    {
        return $this->hasOne(
            KlasifikasiKualifikasi::class,
            'id_izin',
            'id_izin'
        );
    }
    public function jadwal_bnsp_table()
    {
        return $this->hasMany(JadwalBNSP::class, 'id_izin');
    }
    public function data_pencatatans()
    {
        return $this->hasMany(DataPencatatan::class, 'id_izin');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'propinsi', 'id_provinsi_dagri');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'kabupaten', 'kode_kabupaten_dagri');
    }
}
