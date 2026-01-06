<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jenjang extends Model
{
    protected $table = 'jenjang_siki_bnsp';

    protected $fillable = [
        'id_siki',
        'id_bnsp',
        'nama'
    ];
}
