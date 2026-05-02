<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MidtransTransaction extends Model
{
    /**
     * Primary key
     */
    protected $primaryKey = 'id';

    /**
     * Primary key type
     */
    protected $keyType = 'int';

    /**
     * Auto increment
     */
    public $incrementing = true;

    /**
     * Enable timestamps
     */
    public $timestamps = true;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'order_id',
        'invoice_number',
        'transaction_id',
        'status',
        'payment_type',
        'gross_amount',
        'bank',
        'va_number',
        'payment_details',
        'callback_response',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'gross_amount' => 'decimal:2',
        'payment_details' => 'array',
        'callback_response' => 'array',
    ];

    /**
     * Relationship: Midtrans transaction belongs to Invoice
     */
    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class,
            'invoice_number',      // FK on midtrans_transactions
            'invoice_number'       // PK on invoices
        );
    }

    /**
     * Scope: Filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Filter successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', ['capture', 'settlement']);
    }

    /**
     * Scope: Filter failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['cancel', 'deny', 'expire']);
    }

    /**
     * Check if transaction is successful
     */
    public function isSuccessful()
    {
        return in_array($this->status, ['capture', 'settlement']);
    }

    /**
     * Check if transaction is pending
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction is failed
     */
    public function isFailed()
    {
        return in_array($this->status, ['cancel', 'deny', 'expire']);
    }

    /**
     * Get VA number for payment
     */
    public function getVaNumber()
    {
        if ($this->payment_details && isset($this->payment_details['va_numbers'])) {
            return $this->payment_details['va_numbers'][0]['va_number'] ?? null;
        }

        return $this->va_number;
    }

    /**
     * Get bank name for payment
     */
    public function getBank()
    {
        if ($this->payment_details && isset($this->payment_details['va_numbers'])) {
            return $this->payment_details['va_numbers'][0]['bank'] ?? null;
        }

        return $this->bank;
    }
}