<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class JenjangJabatanKerja extends Model
{
    use HasUuids;

    protected $table = 'jenjang_jabatan_kerjas';
    protected $fillable = ['name', 'jabatan_kerja_id', 'jenjang_id'];

    public function jabatanKerja()
    {
        return $this->belongsTo(JabatanKerja::class, 'jabatan_kerja_id', 'id_jabatan_kerja');
    }

    public function jenjang()
    {
        return $this->belongsTo(Jenjang::class);
    }
}
