<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Negara extends Model
{
    use HasUuids;

    protected $table = 'negaras';
    protected $fillable = ['name', 'code'];
}
