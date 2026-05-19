<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MetodePembayaran extends Model
{
    use HasUuids;

    protected $table = 'metode_pembayarans';
    protected $fillable = ['name', 'kode', 'tipe'];
}
