<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ia5Answer extends Model
{
    protected $table = 'ia5_answers';

    protected $fillable = [
        'id_izin',
        'dokumen_id',
        'soal_id',
        'jawaban',
    ];

    public $timestamps = true;
}
