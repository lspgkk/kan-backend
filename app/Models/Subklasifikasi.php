<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subklasifikasi extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $fillable = [
        'kualifikasi_id',
        'kualifikasi',
        'kualifikasi_en',
    ];

    public $timestamps = true;
}
