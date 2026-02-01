<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanKelompokUnit extends Model
{
    protected $table = 'jabatan_kelompok_units';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_kelompok',
        'id_unit',
        'urut',
    ];

    protected $casts = [
        'id_kelompok' => 'integer', // ✅ FIXED
        'id_unit'     => 'integer',
        'urut'        => 'integer',
    ];

    public function kelompok()
    {
        return $this->belongsTo(JabatanKelompok::class, 'id_kelompok', 'id');
    }

    public function unit()
    {
        return $this->belongsTo(
            UnitKompetensi::class,
            'id_unit', // FK on kelompok_units
            'id'       // PK on unit_kompetensis
        );
    }
}
