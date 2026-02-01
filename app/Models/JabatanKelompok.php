<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanKelompok extends Model
{
    protected $table = 'jabatan_kelompoks';

    protected $primaryKey = 'id';

    public $timestamps = false; // no created_at / updated_at columns

    protected $fillable = [
        'id_dokumen',
        'urut',
        'nama',
    ];

    protected $casts = [
        'id_dokumen' => 'integer',
        'urut' => 'string', // varchar(255)
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships (Optional)
    |--------------------------------------------------------------------------
    */

    public function dokumen()
    {
        return $this->belongsTo(JabatanDokumen::class, 'id_dokumen', 'id');
    }

    public function units()
    {
        return $this->hasMany(JabatanKelompokUnit::class, 'id_kelompok', 'id');
    }
}
