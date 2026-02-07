<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Apl2Answer extends Model
{
    protected $table = 'apl2_answers';

    protected $fillable = [
        'id_izin',
        'unit_id',
        'subunit_id',
        'kompeten',
    ];
}
