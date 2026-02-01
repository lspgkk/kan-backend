<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanDokumen extends Model
{
    protected $table = 'jabatan_dokumens';

    protected $primaryKey = 'id';

    public $timestamps = false; // no created_at / updated_at columns

    protected $fillable = [
        'kode',
        'nama_dokumen',
        'id_jabatan',
        'tipe',
        'inputas',
        'inputfor',
        'dokas',
        'file',
        'id_tmp',
        'sts_old',
        'no_skema_old',
        'is_replyfile',
        'urut',
        'is_active',
    ];

    protected $casts = [
        'tipe' => 'integer',
        'inputas' => 'integer',
        'inputfor' => 'integer',
        'dokas' => 'integer',
        'id_tmp' => 'integer',
        'urut' => 'integer',
        'is_active' => 'integer',
    ];

    public function kelompoks()
    {
        return $this->hasMany(JabatanKelompok::class, 'id_dokumen', 'id');
    }
}
