<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyTuk extends Model
{
    protected $table = 'mytuk';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'id_lsp',
        'kode_tuk',
        'jenis_tuk',
        'nama_tuk',
        'alamat',
        'InputDate',
        'InputBy',
    ];

    protected $casts = [
        'InputDate' => 'datetime',
    ];
}
