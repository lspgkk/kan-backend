<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KanSoal extends Model
{
    protected $table = 'kan_soals';

    protected $fillable = [
        'id_dokumen',
        'id_unitkompetensi',
        'id_subunitkompetensi',
        'id_kuk',
        'urut',
        'no',
        'jenis',
        'tipe',
        'soal',
        'soal1',
        'soal2',
        'jawab_a',
        'jawab_b',
        'jawab_c',
        'jawab_d',
        'file_a',
        'file_b',
        'file_c',
        'file_d',
        'jawaban',
        'is_komentar',
    ];

    protected $casts = [
        'tipe' => 'integer',
        'is_komentar' => 'boolean',
    ];

    public function unit()
    {
        return $this->belongsTo(UnitKompetensi::class, 'id_unitkompetensi', 'id');
    }

    public function kuk()
    {
        return $this->belongsTo(Kuk::class, 'id_kuk', 'id');
    }

    public function subunit()
    {
        return $this->belongsTo(SubunitKompetensi::class, 'id_subunitkompetensi', 'id');
    }
}
