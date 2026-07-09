<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    protected $fillable = [
        'id_user',
        'id_jadwal',
        'id_izin',
        'kan_praktisi_jabatan_id',
        'url_image',
        'url_ttd',
        'id_role',
        'jenis_dokumen',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function kanPraktisiJabatan()
    {
        return $this->belongsTo(KanPraktisiJabatan::class, 'kan_praktisi_jabatan_id', 'id');
    }
}
