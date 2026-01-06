<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HargaInvoice extends Model
{
    protected $table = 'harga_invoices';

    protected $fillable = [
        'tipe',
        'jenjang',
        'tahap1',
        'tahap2',
    ];

    public $timestamps = true;
}
