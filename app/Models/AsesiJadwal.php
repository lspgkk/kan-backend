<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsesiJadwal extends Model
{
    protected $fillable = [
        'jadwal_id',
        'id_izin',
        'account',
        'password_plain',
        'kompeten',
        'is_started',
        'started_at'
    ];

    protected $hidden = [
        'password_plain',
    ];
    
    protected $casts = [
        'is_started' => 'boolean',
    ];

    public function personal()
    {
        return $this->belongsTo(
            Personal::class,
            'id_izin',   // foreign key on asesijadwal
            'id_izin'    // primary key on personals
        );
    }

    public function jadwal()
    {
        return $this->belongsTo(
            Jadwal::class,
            'jadwal_id',
            'jadwal_id'
        );
    }
}
