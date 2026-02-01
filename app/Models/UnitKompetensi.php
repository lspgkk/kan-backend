<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitKompetensi extends Model
{
    protected $table = 'unit_kompetensis';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'kode',
        'nama',
        'id_jabatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subunits()
    {
        return $this->hasMany(SubunitKompetensi::class, 'id_unitkompetensi', 'id');
    }

    public function kuks()
    {
        return $this->hasManyThrough(
            Kuk::class,
            SubunitKompetensi::class,
            'id_unitkompetensi',      // FK on subunit table
            'id_subunitkompetensi',   // FK on kuk table
            'id',                     // PK on unit
            'id'                      // PK on subunit
        );
    }
}
