<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanAsesorJabatan extends Model
{
    protected $table = 'kan_asesor_jabatan';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jabatanKerja()
    {
        return $this->belongsTo(JabatanKerja::class, 'id_jabatan_kerja', 'id_jabatan_kerja');
    }
}
