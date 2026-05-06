<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MidtransTransaction;
use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    protected $midtransService;

    public function __construct()
    {
        $this->midtransService = new \App\Services\MidtransService();
    }

    /**
     * Handle Midtrans payment notification webhook
     */
    public function handlePaymentNotification(Request $request)
    {
        try {
            Log::info('Midtrans webhook received', [
                'order_id' => $request->input('order_id'),
                'status_code' => $request->input('status_code'),
                'transaction_status' => $request->input('transaction_status'),
                'all_data' => $request->all(),
            ]);

            // Verify webhook signature
            if (!$this->midtransService->verifyWebhookSignature($request)) {
                Log::error('Invalid Midtrans webhook signature', [
                    'order_id' => $request->input('order_id'),
                    'signature_key' => $request->input('signature_key'),
                ]);

                return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            }

            $orderId = $request->input('order_id');
            $transactionStatus = $request->input('transaction_status');
            $fraudStatus = $request->input('fraud_status');
            $statusCode = $request->input('status_code');

            // Find the Midtrans transaction
            $midtransTransaction = MidtransTransaction::where('order_id', $orderId)->first();

            if (!$midtransTransaction) {
                Log::error('Midtrans transaction not found', [
                    'order_id' => $orderId,
                ]);

                return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
            }

            // Update transaction with callback data
            $midtransTransaction->update([
                'status' => $transactionStatus,
                'transaction_id' => $request->input('transaction_id'),
                'callback_response' => $request->all(),
            ]);

            // Find the invoice
            $invoice = Invoice::where('invoice_number', $midtransTransaction->invoice_number)->first();

            if (!$invoice) {
                Log::error('Invoice not found for Midtrans transaction', [
                    'order_id' => $orderId,
                    'invoice_number' => $midtransTransaction->invoice_number,
                ]);

                return response()->json(['status' => 'error', 'message' => 'Invoice not found'], 404);
            }

            // Process payment based on transaction status
            if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
                if ($fraudStatus === 'accept' || $fraudStatus === null) {
                    // Payment successful - process automatically
                    Log::info('Processing successful Midtrans payment', [
                        'order_id' => $orderId,
                        'invoice_number' => $midtransTransaction->invoice_number,
                        'current_payment_status' => $invoice->payment_status,
                        'transaction_status' => $transactionStatus,
                        'fraud_status' => $fraudStatus,
                    ]);

                    return $this->processSuccessfulPayment($invoice, $midtransTransaction, $request);
                }
            } elseif ($transactionStatus === 'cancel' || $transactionStatus === 'deny' || $transactionStatus === 'expire') {
                // Payment failed or expired
                return $this->processFailedPayment($invoice, $midtransTransaction, $transactionStatus);
            } elseif ($transactionStatus === 'pending') {
                // Payment is still pending
                Log::info('Midtrans payment pending', [
                    'order_id' => $orderId,
                    'invoice_number' => $midtransTransaction->invoice_number,
                ]);

                return response()->json(['status' => 'success', 'message' => 'Payment pending']);
            }

            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);

        } catch (\Exception $e) {
            Log::error('Error processing Midtrans webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Process successful payment - automatic processing
     */
    protected function processSuccessfulPayment($invoice, $midtransTransaction, $request)
    {
        DB::beginTransaction();

        try {
            // Check if invoice is already paid to avoid duplicate processing
            if ($invoice->payment_status === 'paid') {
                Log::info('Invoice already paid, skipping duplicate processing', [
                    'invoice_number' => $invoice->invoice_number,
                    'order_id' => $midtransTransaction->order_id,
                ]);

                DB::rollBack();
                return response()->json(['status' => 'success', 'message' => 'Invoice already paid']);
            }

            // Update invoice payment status
            $invoice->update([
                'payment_status' => 'paid',
                'midtrans_paid_at' => now(),
                'bayar_tahap2' => $invoice->total_biaya,
                'tgl_tahap2' => now()->toDateString(),
                'kode_bayar2' => $request->input('transaction_id'),
            ]);

            // TEMPORARILY DISABLED: SIKI status update after Midtrans payment
            foreach ($invoice->details as $asesi) {
                $this->updateSikiStatusAfterPayment(
                    $asesi->id_izin,
                    '31',
                    'Konfirmasi Pembayaran Midtrans'
                );
            }

            DB::commit();

            Log::info('Midtrans payment processed successfully', [
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $midtransTransaction->order_id,
                'amount' => $invoice->total_biaya,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Payment processed successfully']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to process successful payment', [
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process failed payment
     */
    protected function processFailedPayment($invoice, $midtransTransaction, $status)
    {
        try {
            $invoice->update([
                'payment_status' => 'failed',
                'midtrans_callback_response' => [
                    'status' => $status,
                    'timestamp' => now()->toDateTimeString(),
                ],
            ]);

            Log::info('Midtrans payment failed', [
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $midtransTransaction->order_id,
                'failure_status' => $status,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Payment failure processed']);

        } catch (\Exception $e) {
            Log::error('Failed to process payment failure', [
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update SIKI status after payment (automatic processing)
     * TEMPORARILY DISABLED
     */
    private function updateSikiStatusAfterPayment($id_izin, $statusCode, $keterangan)
    {
        $maxRetries = 4;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $api_url = "https://siki.pu.go.id/siki-api/v1/permohonan-skk/" . $id_izin;

                $headers = [
                    'Content-Type' => 'application/json',
                    'token' => env('SIKI_TOKEN'),
                ];

                $statusPayload = [
                    "kd_status" => $statusCode,
                    "keterangan" => $keterangan
                ];

                $response = Http::withHeaders($headers)
                    ->timeout(30)
                    ->connectTimeout(30)
                    ->post($api_url, $statusPayload);

                if ($response->successful()) {
                    $responseData = $response->json();

                    if ($responseData['status'] == "success") {
                        // Update Personal status
                        Personal::where('id_izin', $id_izin)->update(['status' => (int)$statusCode]);

                        Log::info("SIKI status updated successfully after payment", [
                            'id_izin' => $id_izin,
                            'status' => $statusCode,
                            'keterangan' => $keterangan
                        ]);

                        return;
                    } elseif ($responseData['status'] == "errors") {
                        Log::error("SIKI API returned error after payment", [
                            'id_izin' => $id_izin,
                            'message' => $responseData['message'] ?? null,
                        ]);

                        throw new \Exception("SIKI API error: " . ($responseData['message'] ?? 'Unknown error'));
                    }
                } else {
                    $errorMessage = $response->json()['message'] ?? null;

                    // Handle status already set error
                    if ($errorMessage === 'tidak dapat mengirim kode status 31, kode status saat ini 31') {
                        Personal::where('id_izin', $id_izin)->update(['status' => 31]);
                        return;
                    }

                    throw new \Exception("SIKI API HTTP error: " . $errorMessage);
                }

            } catch (\Exception $e) {
                $attempt++;
                Log::warning("Attempt $attempt: Failed to update SIKI status after payment", [
                    'id_izin' => $id_izin,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $maxRetries) {
                    Log::error("Failed to update SIKI status after maximum retries", [
                        'id_izin' => $id_izin,
                        'status' => $statusCode
                    ]);
                    throw new \Exception("Failed to update SIKI status after $maxRetries attempts");
                }

                // Wait before retry
                sleep(1);
            }
        }
    }
}