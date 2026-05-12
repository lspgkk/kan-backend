<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponseDetail extends Model
{
    protected $fillable = [
        'survey_response_id',
        'pertanyaan_id',
        'skor',
    ];

    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function pertanyaan()
    {
        return $this->belongsTo(SurveyPertanyaan::class, 'pertanyaan_id');
    }
}
