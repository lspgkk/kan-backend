<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferensiKategori extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'kategori',
        'nama',
        'urut',
        'id_kelompok',
    ];

    protected $casts = [
        'urut'        => 'integer',
        'id_kelompok' => 'integer',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function kelompok()
    {
        return $this->belongsTo(
            ReferensiKelompok::class,
            'id_kelompok',
            'id'
        );
    }

    public function subkategoris()
    {
        return $this->hasMany(
            ReferensiSubkategori::class,
            'id_kategori',
            'id'
        );
    }

    public function referensis()
    {
        return $this->hasMany(
            Referensi::class,
            'id_kategori',
            'id'
        );
    }
}
