<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Apl2Submission extends Model
{
    protected $table = 'apl2_submissions';

    protected $fillable = [
        'id_izin',
        'metode',
        'is_dilanjutkan',
    ];

    protected $casts = [
        'is_dilanjutkan' => 'boolean',
    ];
}
