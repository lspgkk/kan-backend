<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Pekerjaan extends Model
{
    use HasUuids;

    protected $table = 'pekerjaans';
    protected $fillable = ['name', 'kode', 'deskripsi'];
}
