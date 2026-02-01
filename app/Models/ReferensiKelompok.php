<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferensiKelompok extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'kategori',
        'nama',
        'urut',
    ];

    protected $casts = [
        'urut' => 'integer',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function kategoris()
    {
        return $this->hasMany(
            ReferensiKategori::class,
            'id_kelompok',
            'id'
        );
    }

    public function referensis()
    {
        return $this->hasMany(
            Referensi::class,
            'id_kelompok',
            'id'
        );
    }
}

