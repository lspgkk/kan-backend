<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanConfigA extends Model
{
    protected $table = 'jabatan_config_a';

    protected $guarded = [];

    public $timestamps = false;

    public function penyusun()
    {
        return $this->belongsTo(User::class, 'penyusun_id', 'id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validator_id', 'id');
    }

    public function manajer()
    {
        return $this->belongsTo(User::class, 'manajer_id', 'id');
    }

    public function kanPenyusun()
    {
        return $this->belongsTo(User::class, 'kan_penyusun_id', 'id');
    }

    public function kanValidator()
    {
        return $this->belongsTo(User::class, 'kan_validator_id', 'id');
    }

    public function kanManajer()
    {
        return $this->belongsTo(User::class, 'kan_manajer_id', 'id');
    }

    public function jabatan()
    {
        return $this->belongsTo(JabatanKerja::class, 'id_jabatan', 'id_jabatan_kerja');
    }
}
