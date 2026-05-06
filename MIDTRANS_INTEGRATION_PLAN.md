# Midtrans Payment Integration Plan

## Context
The current invoice system uses a manual payment flow where invoices are created and then manually confirmed by administrators. Users want to add Midtrans payment integration while maintaining the existing manual option. The system should support both payment methods (user choice during invoice creation), add payment functionality to the existing History page, restrict access to TUK/super admins, and automatically process successful payments with full SIKI status updates.

## Implementation Strategy

### Phase 1: Backend Foundation

#### 1.1 Database Schema Updates
**File**: `database/migrations/YYYY_MM_DD_HHMMSS_add_midtrans_fields_to_invoices_table.php`

Add Midtrans-specific fields to track payment information:
```php
$table->string('payment_method')->default('manual')->after('input_by');
$table->string('payment_status')->default('unpaid')->after('payment_method');
$table->string('midtrans_order_id')->nullable()->after('payment_status');
$table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
$table->string('midtrans_payment_type')->nullable()->after('midtrans_transaction_id');
$table->string('midtrans_va_number')->nullable()->after('midtrans_payment_type');
$table->text('midtrans_callback_response')->nullable()->after('midtrans_va_number');
$table->timestamp('midtrans_paid_at')->nullable()->after('midtrans_callback_response');
```

**Create new table**: `database/migrations/YYYY_MM_DD_HHMMSS_create_midtrans_transactions_table.php`
```php
Schema::create('midtrans_transactions', function (Blueprint $table) {
    $table->id();
    $table->string('order_id')->unique();
    $table->string('invoice_number');
    $table->string('transaction_id')->nullable();
    $table->string('status');
    $table->string('payment_type');
    $table->decimal('gross_amount', 15, 2);
    $table->string('bank')->nullable();
    $table->string('va_number')->nullable();
    $table->json('payment_details')->nullable();
    $table->json('callback_response')->nullable();
    $table->timestamps();

    $table->foreign('invoice_number')->references('invoice_number')->on('invoices')->onDelete('cascade');
});
```

#### 1.2 Configuration Setup
**File**: `config/services.php`
Add Midtrans configuration following existing patterns:
```php
'midtrans' => [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'environment' => env('MIDTRANS_ENVIRONMENT', 'sandbox'),
    'prefix' => env('MIDTRANS_PREFIX', 'GATENSI'),
    'payment_methods' => ['credit_card', 'bca_va', 'bni_va', 'mandiri_va', 'gopay'],
],
```

**File**: `.env`
Add environment variables:
```env
MIDTRANS_SERVER_KEY=SB-Mid-server-t-7qU2q-Ty9Kj-Wg4EfenDR0
MIDTRANS_CLIENT_KEY=SB-Mid-client-OvfocJ6Na0ed_3Aj
MIDTRANS_ENVIRONMENT=sandbox
MIDTRANS_PREFIX=GATENSI
```

#### 1.3 Service Layer Creation
**File**: `app/Services/MidtransService.php`
Create dedicated service following existing HTTP client patterns:
- `createTransaction($invoice, $paymentMethod)` - Create Midtrans transaction
- `checkStatus($orderId)` - Check payment status
- `verifyWebhookSignature($request)` - Validate webhook signatures
- `handleWebhook($request)` - Process payment notifications
- `getPaymentUrl($transaction)` - Get payment redirect URL

#### 1.4 Model Updates
**File**: `app/Models/Invoice.php`
Add payment relationships and scopes:
```php
protected $fillable = [
    // existing fields...
    'payment_method', 'payment_status', 'midtrans_order_id',
    'midtrans_transaction_id', 'midtrans_payment_type', 'midtrans_va_number',
    'midtrans_callback_response', 'midtrans_paid_at'
];

public function midtransTransaction()
{
    return $this->hasOne(MidtransTransaction::class, 'invoice_number', 'invoice_number');
}

public function scopeUnpaid($query)
{
    return $query->where('payment_status', 'unpaid');
}

public function isPaidViaMidtrans()
{
    return $this->payment_method === 'midtrans' && $this->payment_status === 'paid';
}
```

**File**: `app/Models/MidtransTransaction.php`
Create new model for transaction tracking.

### Phase 2: API Endpoint Development

#### 2.1 Enhanced Invoice Controller
**File**: `app/Http/Controllers/InvoiceController.php`

**Modify `createInvoice` method**:
- Add `payment_method` parameter to validation
- When `payment_method === 'midtrans'`, create Midtrans transaction instead of manual payment
- Return different response based on payment method

**Add new methods**:
```php
public function createMidtransPayment(Request $request)
{
    // Create Midtrans transaction for existing unpaid invoice
    // Return payment URL for frontend redirect
}

public function checkMidtransStatus(Request $request, $invoiceNumber)
{
    // Check current status of Midtrans payment
    // Return payment details and status
}

public function getUnpaidInvoices(Request $request)
{
    // Get unpaid invoices for current user's TUK
    // Filter by user permissions (TUK admin, super admin)
}
```

#### 2.2 Webhook Handler
**File**: `app/Http/Controllers/MidtransWebhookController.php`
Create dedicated webhook controller:
```php
public function handlePaymentNotification(Request $request)
{
    // Verify webhook signature
    // Update invoice status to 'paid'
    // Update SIKI status to '31' (Konfirmasi Pembayaran) automatically
    // Update Personal status to '31'
    // Log all activities
}
```

#### 2.3 Route Definitions
**File**: `routes/api.php`
Add new endpoints:
```php
Route::middleware(['auth:api'])->prefix('invoice')->group(function () {
    // Existing routes...
    Route::get('/unpaid', [InvoiceController::class, 'getUnpaidInvoices']);
    Route::post('/{invoice}/midtrans-pay', [InvoiceController::class, 'createMidtransPayment']);
    Route::get('/{invoice}/midtrans-status', [InvoiceController::class, 'checkMidtransStatus']);
});

Route::post('/midtrans/webhook', [MidtransWebhookController::class, 'handlePaymentNotification']);
```

### Phase 3: Frontend Integration

#### 3.1 Enhanced Invoice Creation
**File**: `src/views/home/invoice/CreateView.vue`
Add payment method selection:
```vue
<el-form-item label="Metode Pembayaran">
  <el-select v-model="payload.metode_pembayaran" placeholder="Pilih metode">
    <el-option label="Manual Transfer" value="manual" />
    <el-option label="Midtrans Payment" value="midtrans" />
  </el-select>
</el-form-item>
```

Update `createInvoice` composable to handle different payment method responses.

#### 3.2 Enhanced History Page
**File**: `src/views/home/invoice/HistoryView.vue`
Add payment functionality:
- Filter invoices to show unpaid status
- Add "Bayar Sekarang" button for unpaid invoices with Midtrans
- Integrate Midtrans Snap.js for payment modal
- Update invoice list after successful payment

#### 3.3 Payment Composable
**File**: `src/composables/invoice.ts`
Extend with Midtrans methods:
```typescript
const createMidtransPayment = async (invoiceNumber: string) => {
  // Call API to create Midtrans transaction
  // Open Midtrans Snap modal
}

const checkPaymentStatus = async (invoiceNumber: string) => {
  // Check payment status
  // Update UI accordingly
}
```

#### 3.4 Router Updates
**File**: `src/router/index.ts`
No new routes needed - using existing History page with enhanced functionality.

### Phase 4: Access Control Implementation

#### 4.1 Backend Permissions
**File**: `app/Http/Controllers/InvoiceController.php`
Add role-based filtering in `getUnpaidInvoices`:
```php
public function getUnpaidInvoices(Request $request)
{
    $user = Auth::user();

    if ($user->role === 'superadmin') {
        // Can see all unpaid invoices
        $invoices = Invoice::unpaid()->paginate();
    } elseif ($user->role === 'adm_tuk') {
        // Can only see invoices for their TUK
        $invoices = Invoice::unpaid()
            ->where('nama_tuk', $user->tuk_name)
            ->paginate();
    } else {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    return response()->json($invoices);
}
```

#### 4.2 Frontend Role Checking
**File**: `src/views/home/invoice/HistoryView.vue`
Add role-based UI display:
```typescript
const canPayInvoice = computed(() => {
  const userRole = useAuthStore().user?.role
  return userRole === 'adm_tuk' || userRole === 'superadmin'
})
```

### Phase 5: Automatic Payment Processing

#### 5.1 Webhook Automation
**File**: `app/Http/Controllers/MidtransWebhookController.php`
Implement automatic status updates:
```php
public function handlePaymentNotification(Request $request)
{
    // 1. Verify webhook signature
    if (!$this->midtransService->verifyWebhookSignature($request)) {
        return response()->json(['status' => 'error'], 400);
    }

    // 2. Update invoice status
    $invoice = Invoice::where('midtrans_order_id', $request->order_id)->first();
    $invoice->update([
        'payment_status' => 'paid',
        'midtrans_paid_at' => now(),
        'bayar_tahap2' => $invoice->total_biaya,
        'tgl_tahap2' => now(),
        'kode_bayar2' => $request->transaction_id,
    ]);

    // 3. Update SIKI status automatically (no admin confirmation needed)
    foreach ($invoice->details as $asesi) {
        $this->updateSikiStatus($asesi->id_izin, '31', 'Konfirmasi Pembayaran');
        Personal::where('id_izin', $asesi->id_izin)->update(['status' => 31]);
    }

    return response()->json(['status' => 'success']);
}
```

### Phase 6: Error Handling & Edge Cases

#### 6.1 Payment Failures
- Handle Midtrans API failures gracefully
- Log all errors with context
- Provide user-friendly error messages
- Allow retry for failed payments

#### 6.2 Status Synchronization
- Handle cases where webhook is delayed
- Implement status check on frontend
- Handle duplicate webhook notifications
- Handle payment refunds if needed

#### 6.3 Data Consistency
- Use database transactions for all payment updates
- Implement retry logic for SIKI API calls
- Roll back changes if any step fails
- Log all state changes for audit trail

## Critical Files to Modify

### Backend:
- `config/services.php` - Add Midtrans configuration
- `app/Services/MidtransService.php` - NEW: Midtrans service layer
- `app/Models/Invoice.php` - Add payment relationships
- `app/Models/MidtransTransaction.php` - NEW: Transaction tracking
- `app/Http/Controllers/InvoiceController.php` - Enhance with payment methods
- `app/Http/Controllers/MidtransWebhookController.php` - NEW: Webhook handler
- `routes/api.php` - Add payment endpoints
- Database migrations for schema updates

### Frontend:
- `src/views/home/invoice/CreateView.vue` - Add payment method selection
- `src/views/home/invoice/HistoryView.vue` - Add payment functionality
- `src/composables/invoice.ts` - Extend with payment methods
- `src/types/invoice.ts` - Add Midtrans types

## Verification & Testing

### 1. Backend Testing
- Test invoice creation with both manual and Midtrans payment methods
- Verify Midtrans transaction creation returns valid payment URL
- Test webhook handling with sandbox notifications
- Verify automatic SIKI status updates after payment
- Test role-based access control for unpaid invoices
- Test error scenarios (failed payments, duplicate webhooks, etc.)

### 2. Frontend Testing
- Test payment method selection during invoice creation
- Verify unpaid invoices appear correctly in History page
- Test Midtrans Snap integration and payment flow
- Verify UI updates after successful payment
- Test role-based visibility of payment options
- Test error handling and user feedback

### 3. Integration Testing
- Test end-to-end payment flow: invoice creation → payment → status update → SIKI sync
- Verify database consistency after payment
- Test with Midtrans sandbox credentials provided
- Test concurrent payment scenarios
- Verify no testing with SIKI API as requested

### 4. Security Testing
- Test webhook signature verification
- Verify role-based access control
- Test SQL injection protection
- Verify no sensitive data leakage in API responses

## Rollout Plan

1. **Phase 1**: Backend foundation (DB, config, service layer)
2. **Phase 2**: API endpoints and webhook handling
3. **Phase 3**: Frontend integration with existing pages
4. **Phase 4**: Access control implementation
5. **Phase 5**: Automatic payment processing
6. **Phase 6**: Testing and refinement

## Notes
- No testing with SIKI API as explicitly requested
- Maintain backward compatibility with existing manual payment flow
- Use existing error handling and logging patterns
- Follow Laravel and Vue.js best practices from codebase
- Ensure all payment activities are properly logged for audit purposes