<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Tarif extends Model
{
    use HasUuids;

    protected $table = 'tarifs';
    protected $fillable = ['name', 'jenis', 'nominal'];
}
