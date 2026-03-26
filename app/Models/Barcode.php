<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    protected $fillable = [
        'id_user',
        'id_jadwal',
        'id_izin',
        'url_image',
        'url_ttd',
        'id_role',
        'jenis_dokumen'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }
}
