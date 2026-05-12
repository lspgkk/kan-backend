<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    protected $fillable = [
        'id_izin',
        'kategori',
        'saran',
        'pernyataan',
    ];

    protected $casts = [
        'pernyataan' => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(SurveyResponseDetail::class);
    }
}
