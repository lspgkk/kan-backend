<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class MidtransService
{
    protected $serverKey;
    protected $clientKey;
    protected $environment;
    protected $prefix;
    protected $paymentMethods;

    public function __construct()
    {
        $this->serverKey = Config::get('services.midtrans.server_key');
        $this->clientKey = Config::get('services.midtrans.client_key');
        $this->environment = Config::get('services.midtrans.environment', 'sandbox');
        $this->prefix = Config::get('services.midtrans.prefix', 'GATENSI');
        $this->paymentMethods = Config::get('services.midtrans.payment_methods', []);
    }

    /**
     * Calculate Midtrans fee based on payment method
     */
    protected function calculateMidtransFee($amount, $paymentMethod)
    {
        $fee = 0;

        switch ($paymentMethod) {
            case 'credit_card':
                // 2.9% + IDR 2,000
                $fee = ($amount * 0.029) + 2000;
                break;
            case 'bca_va':
            case 'bni_va':
            case 'mandiri_va':
            case 'permata_va':
            case 'other_va':
                // Flat fee IDR 4,000
                $fee = 4000;
                break;
            case 'gopay':
            case 'shopeepay':
            case 'qris':
                // 2% of transaction amount
                $fee = $amount * 0.02;
                break;
            case 'bca_klikpay':
            case 'cimb_clicks':
                // 4.5% of transaction amount + IDR 2,000
                $fee = ($amount * 0.045) + 2000;
                break;
            case 'bri_epay':
                // 3.2% of transaction amount + IDR 2,000
                $fee = ($amount * 0.032) + 2000;
                break;
            default:
                // Default VA fee
                $fee = 4000;
                break;
        }

        // Ensure minimum fee
        return max($fee, 0);
    }

    /**
     * Get fee description for payment method
     */
    protected function getFeeDescription($paymentMethod)
    {
        $descriptions = [
            'credit_card' => 'Biaya Kartu Kredit (2.9% + Rp 2.000)',
            'bca_va' => 'Biaya Admin BCA VA (Rp 4.000)',
            'bni_va' => 'Biaya Admin BNI VA (Rp 4.000)',
            'mandiri_va' => 'Biaya Admin Mandiri VA (Rp 4.000)',
            'permata_va' => 'Biaya Admin Permata VA (Rp 4.000)',
            'gopay' => 'Biaya Admin GoPay (2%)',
            'shopeepay' => 'Biaya Admin ShopeePay (2%)',
            'qris' => 'Biaya Admin QRIS (2%)',
            'bca_klikpay' => 'Biaya Admin BCA Klikpay (4.5% + Rp 2.000)',
            'cimb_clicks' => 'Biaya Admin CIMB Clicks (4.5% + Rp 2.000)',
            'bri_epay' => 'Biaya Admin BRI E-Pay (3.2% + Rp 2.000)',
        ];

        return $descriptions[$paymentMethod] ?? 'Biaya Admin';
    }

    /**
     * Create Midtrans transaction with customer-paid fees
     */
    public function createTransaction($invoice, $paymentMethod = 'bca_va')
    {
        $orderId = $this->generateOrderId($invoice->invoice_number);

        // Calculate Midtrans fee to be paid by customer
        $baseAmount = (int) $invoice->total_biaya;
        $midtransFee = $this->calculateMidtransFee($baseAmount, $paymentMethod);
        $totalAmount = $baseAmount + $midtransFee; // Customer pays original amount + fee

        $transactionData = [
            'payment_type' => $paymentMethod,
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $totalAmount, // Include fees in amount
            ],
            'customer_details' => [
                'first_name' => $invoice->nama_tuk,
                'email' => 'admin@gatensi.id',
                'phone' => '08123456789',
            ],
            'item_details' => [
                [
                    'id' => $invoice->invoice_number,
                    'name' => 'Invoice ' . $invoice->invoice_number,
                    'quantity' => 1,
                    'price' => $baseAmount, // Original invoice amount
                ],
                [
                    'id' => 'midtrans_fee',
                    'name' => $this->getFeeDescription($paymentMethod),
                    'quantity' => 1,
                    'price' => (int) $midtransFee, // Fee paid by customer
                ],
            ],
                ]
            ],
        ];

        // Add payment method specific details
        if ($paymentMethod === 'bca_va') {
            $transactionData['bca_va'] = [
                'va_number' => $this->generateVANumber(),
            ];
        } elseif ($paymentMethod === 'bni_va') {
            $transactionData['bni_va'] = [
                'va_number' => $this->generateVANumber(),
            ];
        } elseif ($paymentMethod === 'mandiri_va') {
            $transactionData['mandiri_va'] = [
                'va_number' => $this->generateVANumber(),
            ];
        }

        try {
            Log::info('Midtrans transaction creation started', [
                'order_id' => $orderId,
                'invoice_number' => $invoice->invoice_number,
                'base_amount' => $baseAmount,
                'midtrans_fee' => $midtransFee,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->serverKey . ':'),
            ])->timeout(30)
              ->connectTimeout(30)
              ->retry(3, 1000)
              ->post($this->getApiUrl(), $transactionData);

            if ($response->successful()) {
                $responseData = $response->json();

                // Validate response structure
                if (!isset($responseData['order_id'], $responseData['status_code'])) {
                    throw new Exception('Invalid Midtrans response structure');
                }

                Log::info('Midtrans transaction created successfully', [
                    'order_id' => $orderId,
                    'status_code' => $responseData['status_code'],
                    'transaction_id' => $responseData['transaction_id'] ?? null,
                    'payment_type' => $responseData['payment_type'] ?? null,
                    'base_amount' => $baseAmount,
                    'midtrans_fee' => $midtransFee,
                    'total_amount' => $totalAmount,
                ]);

                return $responseData;
            }

            Log::error('Midtrans transaction failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new Exception('Midtrans API returned status ' . $response->status() . ': ' . $response->body());

        } catch (Exception $e) {
            Log::error('Midtrans transaction creation error', [
                'order_id' => $orderId,
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check transaction status
     */
    public function checkStatus($orderId)
    {
        try {
            Log::info('Checking Midtrans transaction status', ['order_id' => $orderId]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->serverKey . ':'),
            ])->timeout(30)
              ->connectTimeout(30)
              ->get($this->getApiUrl() . '/' . $orderId . '/status');

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Midtrans status check successful', [
                    'order_id' => $orderId,
                    'status' => $responseData['transaction_status'] ?? null,
                ]);
                return $responseData;
            }

            Log::error('Midtrans status check failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new Exception('Failed to check Midtrans status');

        } catch (Exception $e) {
            Log::error('Midtrans status check error', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($request)
    {
        $orderId = $request->input('order_id');
        $statusCode = $request->input('status_code');
        $grossAmount = $request->input('gross_amount');
        $signatureKey = $request->input('signature_key');

        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        $isValid = $signatureKey === $expectedSignature;

        if (!$isValid) {
            Log::error('Invalid Midtrans webhook signature', [
                'order_id' => $orderId,
                'received_signature' => $signatureKey,
                'expected_signature' => $expectedSignature,
            ]);
        }

        return $isValid;
    }

    /**
     * Get fee breakdown for display
     */
    public function getFeeBreakdown($amount, $paymentMethod)
    {
        $midtransFee = $this->calculateMidtransFee($amount, $paymentMethod);
        $totalAmount = $amount + $midtransFee;

        return [
            'base_amount' => $amount,
            'midtrans_fee' => $midtransFee,
            'total_amount' => $totalAmount,
            'fee_description' => $this->getFeeDescription($paymentMethod),
            'payment_method' => $paymentMethod,
        ];
    }

    /**
     * Get payment redirect URL
     */
    public function getPaymentUrl($transaction)
    {
        if ($this->environment === 'production') {
            return 'https://app.midtrans.com/ payment/' . $transaction['order_id'] ?? '';
        }

        return 'https://app.sandbox.midtrans.com/ payment/' . $transaction['order_id'] ?? '';
    }

    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods()
    {
        return array_keys(array_filter($this->paymentMethods, fn($enabled) => $enabled));
    }

    /**
     * Get supported payment methods with fees
     */
    public function getSupportedPaymentMethods()
    {
        return [
            'credit_card' => [
                'name' => 'Kartu Kredit',
                'fee_type' => 'percentage',
                'fee_rate' => 2.9,
                'fixed_fee' => 2000,
                'description' => '2.9% + Rp 2.000',
            ],
            'bca_va' => [
                'name' => 'BCA Virtual Account',
                'fee_type' => 'fixed',
                'fixed_fee' => 4000,
                'description' => 'Rp 4.000',
            ],
            'bni_va' => [
                'name' => 'BNI Virtual Account',
                'fee_type' => 'fixed',
                'fixed_fee' => 4000,
                'description' => 'Rp 4.000',
            ],
            'mandiri_va' => [
                'name' => 'Mandiri Virtual Account',
                'fee_type' => 'fixed',
                'fixed_fee' => 4000,
                'description' => 'Rp 4.000',
            ],
            'gopay' => [
                'name' => 'GoPay',
                'fee_type' => 'percentage',
                'fee_rate' => 2.0,
                'description' => '2%',
            ],
        ];
    }

    /**
     * Generate order ID
     */
    protected function generateOrderId($invoiceNumber)
    {
        return $this->prefix . '-' . $invoiceNumber . '-' . time();
    }

    /**
     * Generate VA number
     */
    protected function generateVANumber()
    {
        return rand(1000000000, 9999999999);
    }

    /**
     * Get API URL based on environment
     */
    protected function getApiUrl()
    {
        $baseUrl = $this->environment === 'production'
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';

        return $baseUrl . '/charge';
    }

    /**
     * Get client key for frontend
     */
    public function getClientKey()
    {
        return $this->clientKey;
    }

    /**
     * Check if sandbox mode
     */
    public function isSandbox()
    {
        return $this->environment === 'sandbox';
    }
}