<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soal extends Model
{
    protected $table = 'soals';

    protected $fillable = [
        'id_dokumen',
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
        'is_komentar'
    ];

    public function unit()
    {
        return $this->belongsTo(
            UnitKompetensi::class,
            'id_unitkompetensi', // FK on kelompok_units
            'id'       // PK on unit_kompetensis
        );
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
