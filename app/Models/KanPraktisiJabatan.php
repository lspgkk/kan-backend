<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanPraktisiJabatan extends Model
{
    protected $table = 'kan_praktisi_jabatan';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    public function jabatanKerja()
    {
        return $this->belongsTo(JabatanKerja::class, 'id_jabatan_kerja', 'id_jabatan_kerja');
    }
}
