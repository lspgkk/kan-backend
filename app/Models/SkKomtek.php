<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkKomtek extends Model
{
    protected $fillable = [
        'jadwal_id',
        'no_surat',
        'link',
        'is_approved'
    ];
}
