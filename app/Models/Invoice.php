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
        'payment_method',
        'payment_status',
        'midtrans_order_id',
        'midtrans_transaction_id',
        'midtrans_payment_type',
        'midtrans_va_number',
        'midtrans_callback_response',
        'midtrans_paid_at',
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

    /**
     * Relationship: Invoice has one Midtrans transaction
     */
    public function midtransTransaction()
    {
        return $this->hasOne(
            \App\Models\MidtransTransaction::class,
            'invoice_number',     // FK on midtrans_transactions
            'invoice_number'      // PK on invoices
        );
    }

    /**
     * Scope: Filter unpaid invoices
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    /**
     * Scope: Filter paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * Scope: Filter by payment method
     */
    public function scopePaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Check if invoice is paid via Midtrans
     */
    public function isPaidViaMidtrans()
    {
        return $this->payment_method === 'midtrans' && $this->payment_status === 'paid';
    }

    /**
     * Check if invoice is unpaid
     */
    public function isUnpaid()
    {
        return $this->payment_status === 'unpaid';
    }

    /**
     * Check if invoice uses manual payment
     */
    public function isManualPayment()
    {
        return $this->payment_method === 'manual';
    }
}
