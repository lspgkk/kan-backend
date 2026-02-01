<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubunitKompetensi extends Model
{
    protected $table = 'subunit_kompetensis';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'kode',
        'nama',
        'id_unitkompetensi',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function unit()
    {
        return $this->belongsTo(UnitKompetensi::class, 'id_unitkompetensi', 'id');
    }

    public function kuks()
    {
        return $this->hasMany(Kuk::class, 'id_subunitkompetensi', 'id');
    }
}
