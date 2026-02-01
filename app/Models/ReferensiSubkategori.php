<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferensiSubkategori extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'kategori',
        'id_kategori',
        'nama',
        'urut',
    ];

    protected $casts = [
        'id_kategori' => 'integer',
        'urut'        => 'integer',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function kategori()
    {
        return $this->belongsTo(
            ReferensiKategori::class,
            'id_kategori',
            'id'
        );
    }

    public function referensis()
    {
        return $this->hasMany(
            Referensi::class,
            'id_subkategori',
            'id'
        );
    }
}
