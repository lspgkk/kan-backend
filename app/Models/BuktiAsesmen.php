<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuktiAsesmen extends Model
{
    use HasFactory;

    protected $table = 'bukti_asesmens';
    protected $guarded = ['id'];
}
