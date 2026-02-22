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
}
