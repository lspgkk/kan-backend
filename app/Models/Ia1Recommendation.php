<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia1Recommendation extends Model
{
    protected $fillable = [
        'id_izin',
        'is_kompeten'
    ];

    protected $casts = [
        'is_kompeten' => 'boolean'
    ];
}
 