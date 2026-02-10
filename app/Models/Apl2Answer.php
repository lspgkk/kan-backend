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

    public function files()
    {
        return $this->belongsToMany(
            Apl2File::class,
            'apl2_answer_files',
            'apl2_answer_id',
            'apl2_file_id'
        );
    }
}
