<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Diskon extends Model
{
    use HasUuids;

    protected $table = 'diskons';
    protected $fillable = ['name', 'persentase', 'deskripsi'];
}
