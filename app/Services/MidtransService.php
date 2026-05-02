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
     * Create Midtrans transaction
     */
    public function createTransaction($invoice, $paymentMethod = 'bca_va')
    {
        $orderId = $this->generateOrderId($invoice->invoice_number);

        $transactionData = [
            'payment_type' => $paymentMethod,
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $invoice->total_biaya,
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
                    'price' => (int) $invoice->total_biaya,
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
                'amount' => $invoice->total_biaya,
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