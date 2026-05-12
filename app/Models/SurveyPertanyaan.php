<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyPertanyaan extends Model
{
    protected $fillable = [
        'kategori',
        'nomor',
        'aspek',
        'pertanyaan',
    ];
}
