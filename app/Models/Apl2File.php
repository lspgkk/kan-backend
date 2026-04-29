<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Apl2File extends Model
{
    protected $table = 'apl2_files';

    protected $fillable = [
        'id_izin',
        'original_name',
        'path',
        'mime',
        'size',
        'filetype',
    ];

    public $timestamps = true;

    /**
     * Answers that use this file
     */
    public function answers()
    {
        return $this->belongsToMany(
            Apl2Answer::class,
            'apl2_answer_files',
            'apl2_file_id',
            'apl2_answer_id'
        );
    }

    /**
     * Optional helper for full URL
     */
    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }
}
