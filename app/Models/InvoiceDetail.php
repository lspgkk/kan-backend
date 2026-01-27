<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    /**
     * Table name
     */

    /**
     * Primary key
     */
    protected $primaryKey = 'id';

    /**
     * Auto increment
     */
    public $incrementing = true;

    /**
     * Primary key type
     */
    protected $keyType = 'int';

    /**
     * Enable timestamps
     */
    public $timestamps = true;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'no_invoice',
        'id_izin',
        'metode_pembayaran',
        'tahap_invoice',
        'jenis_permohonan',
        'biaya',
        'deposit',
        'ditagihkan',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'biaya'   => 'float',
        'deposit' => 'float',
    ];

    /**
     * Relationship: InvoiceDetail belongs to Invoice
     */
    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class,
            'no_invoice',        // FK on invoice_details
            'invoice_number'     // PK on invoices
        );
    }
}
