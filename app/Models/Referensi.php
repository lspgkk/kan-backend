<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referensi extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'kategori',
        'nama',
        'id_kelompok',
        'id_kategori',
        'id_subkategori',
        'no',
        'no_text',
        'keterangan1',
        'keterangan2',
        'keterangan3',
        'isdefault',
    ];

    protected $casts = [
        'id_kelompok'    => 'integer',
        'id_kategori'    => 'integer',
        'id_subkategori' => 'integer',
        'no'             => 'integer',
        'isdefault'      => 'integer',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function kelompokRel()
    {
        return $this->belongsTo(
            ReferensiKelompok::class,
            'id_kelompok',
            'id'
        );
    }

    public function kategoriRel()
    {
        return $this->belongsTo(
            ReferensiKategori::class,
            'id_kategori',
            'id'
        );
    }

    public function subkategoriRel()
    {
        return $this->belongsTo(
            ReferensiSubkategori::class,
            'id_subkategori',
            'id'
        );
    }

    public function ak07Answer()
    {
        return $this->hasOne(Ak7Answer::class, 'referensi_id', 'id');
    }

    public function ak04Answer()
    {
        return $this->hasOne(Ak4Answer::class, 'referensi_id', 'id');
    }
}