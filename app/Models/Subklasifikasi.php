<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subklasifikasi extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $fillable = [
        'id_klasifikasi',
        'kode_subklasifikasi',
        'deskripsi_subklasifikasi',
    ];

    public $timestamps = true;
}
