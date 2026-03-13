<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyTukBnsp extends Model
{
    protected $table = 'mytukbnsp';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_lsp',
        'kode',
        'nama',
        'id_jenis',
        'id_provinsi',
        'id_kota',
        'alamat',
        'lokasi',
        'telp',
        'hp',
        'fax',
        'email',
        'keterangan',
        'jumlah_skema',
        'status',
        'status_skema',
        'kode_provinsi',
        'kode_kota',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'id_lsp' => 'integer',
        'id_jenis' => 'integer',
        'id_provinsi' => 'integer',
        'id_kota' => 'integer',
        'jumlah_skema' => 'integer',
        'status' => 'integer',
        'status_skema' => 'integer',
        'created_at' => 'datetime',
    ];
}
