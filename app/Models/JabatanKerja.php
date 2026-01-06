<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanKerja extends Model
{
    protected $primaryKey = 'id_jabatan_kerja';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'lsp_id_klasifikasi',
        'klasifikasi',
        'klasifikasi_en',
        'lsp_sub_klasifikasi_id',
        'subklasifikasi',
        'subklasifikasi_en',
        'lsp_kualifikasi_id',
        'kualifikasi',
        'kualifikasi_en',
        'id_jabker',
        'id_jabatan_kerja',
        'jabatan_kerja',
        'work_position',
        'jenjang_id',
        'acuan',
        'keterangan',
        'status',
        'inputdate',
        'inputby',
        'sinserver',
        'peralatan',
        'persyaratan_dasar',
    ];
}
