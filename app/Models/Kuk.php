<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kuk extends Model
{
    protected $table = 'kuks';

    protected $primaryKey = 'id';

    public $timestamps = true; // created_at & updated_at exist

    protected $fillable = [
        'kode',
        'nama',
        'id_subunitkompetensi',
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

    public function subunit()
    {
        return $this->belongsTo(SubunitKompetensi::class, 'id_subunitkompetensi', 'id');
    }
}
