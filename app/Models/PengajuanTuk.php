<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanTuk extends Model
{
    protected $table = 'pengajuan_tuk';

    protected $fillable = [
        'ref_number',
        'tuk_type',
        'status',
        'data_tuk',
        'asesor_list',
        'data_sewaktu',
        'dokumen_permohonan',
        'lampiran',
        'submitted_by',
    ];

    protected $casts = [
        'data_tuk' => 'array',
        'asesor_list' => 'array',
        'data_sewaktu' => 'array',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
