<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /**
     * Primary key
     */
    protected $primaryKey = 'invoice_number';

    /**
     * Primary key type
     */
    protected $keyType = 'string';

    /**
     * Disable auto-increment
     */
    public $incrementing = false;

    /**
     * Disable default timestamps (created_at, updated_at)
     * because you use input_date instead
     */
    public $timestamps = false;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'invoice_number',
        'nama',
        'inv_type',
        'bulan',
        'tahun',
        'nomor',
        'jatuh_tempo',
        'nama_tuk',
        'class_uji',
        'kode_rekening',
        'keterangan',
        'total_biaya',
        'bayar_tahap1',
        'tgl_tahap1',
        'kode_bayar1',
        'input_bayar1',
        'bayar_tahap2',
        'tgl_tahap2',
        'kode_bayar2',
        'input_bayar2',
        'refund_bayar',
        'tgl_uji',
        'status_jadwal',
        'inv_link',
        'inv_excel',
        'input_date',
        'input_by',
        'sin_server',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'inv_type'       => 'integer',
        'bulan'          => 'integer',
        'tahun'          => 'integer',
        'nomor'          => 'integer',
        'jatuh_tempo'    => 'integer',
        'total_biaya'    => 'float',
        'bayar_tahap1'   => 'float',
        'bayar_tahap2'   => 'float',
        'refund_bayar'   => 'float',
        'status_jadwal'  => 'integer',
        'tgl_tahap1'     => 'date',
        'tgl_tahap2'     => 'date',
        'tgl_uji'        => 'date',
        'input_date'     => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(
            InvoiceDetail::class,
            'no_invoice',        // FK on invoice_details
            'invoice_number'     // PK on invoices
        );
    }
}
