# AlRajhi Payment Gateway (Laravel Package)

Lightweight Laravel package for Al Rajhi Bank Payment Gateway integration.

## What this package provides

- Bank Hosted payment initiation
- Faster Checkout support
- Iframe support
- Apple Pay (Bank Hosted path)
- Webhook processing service
- BIN check helper
- Unified response normalization and exception shape
- UDF1..UDF10 forwarding + validation
- Clean internal architecture (orchestrators + dedicated services)

## Installation

```bash
composer require alrajhi/payment-gateway
php artisan vendor:publish --tag=alrajhi-config
```

## Configuration

Set these values in your `.env`:

```env
ALRAJHI_BASE_URL=https://securepayments.alrajhibank.com.sa
ALRAJHI_ENVIRONMENT=sandbox
ALRAJHI_TRANPORTAL_ID=your_tranportal_id
ALRAJHI_TRANPORTAL_PASSWORD=your_password
ALRAJHI_RESOURCE_KEY=your_resource_key

# Compatibility toggles for trandata encryption/decryption
ALRAJHI_URL_ENCODE_BEFORE_ENCRYPT=false
ALRAJHI_URL_DECODE_AFTER_DECRYPT=false
ALRAJHI_RETRY_RAW_TRANDATA_ON_INVALID=true

ALRAJHI_RESPONSE_URL=${APP_URL}/api/payment/success
ALRAJHI_ERROR_URL=${APP_URL}/api/payment/failed
ALRAJHI_WEBHOOK_SECRET=your_webhook_secret

ALRAJHI_STRICT_RESPONSE_MODE=false
ALRAJHI_ACCEPT_QUERY_RESPONSE=true
ALRAJHI_ACCEPT_DIRECT_CALLBACK_FIELDS=true
ALRAJHI_SUCCESS_STATUSES=1,success,approved,captured,processing,voided

ALRAJHI_PREFER_CATALOG_MESSAGE=true
ALRAJHI_INCLUDE_OFFICIAL_MESSAGE=true

ALRAJHI_UDF_AUTO_FILL_DEFAULTS=false
ALRAJHI_CAPTURE_AUTO_SET_UDF7_R=true
```

## Quick start

```php
use AlRajhi\PaymentGateway\Facades\AlRajhiPayment;

$payment = AlRajhiPayment::bankHosted()->initiate([
    'id' => env('ALRAJHI_TRANPORTAL_ID'),
    'password' => env('ALRAJHI_TRANPORTAL_PASSWORD'),
    'amount' => '400.00',
    'action' => '1',
    'currencyCode' => '682',
    'trackId' => 'ssjhskkkhw441155',
    'responseURL' => env('ALRAJHI_RESPONSE_URL'),
    'errorURL' => env('ALRAJHI_ERROR_URL'),
]);
```

Success response shape:

```php
[
    'success' => true,
    'payment_id' => 'xxxxxxxx',
    'payment_url' => 'https://securepayments.../tranportal.htm',
    'redirect_url' => 'https://securepayments.../tranportal.htm?PaymentID=xxxxxxxx',
    'track_id' => 'ORDER-123',
]
```

## Flow Charts

### 1) Payment flow (Bank Hosted)

```mermaid
flowchart TD
    A[Merchant Backend: initiate] --> B[Prepare payload\ncallbacks + customer_ip + UDF defaults]
    B --> C[Encrypt trandata\nAES-256-CBC + IV]
    C --> D[Send token request to ARB\nwith X-FORWARDED-FOR]
    D --> E{Initial status == success?}
    E -- No --> F[Throw PaymentGatewayException]
    E -- Yes --> G[Receive paymentId:paymentUrl]
    G --> H[Build redirect_url with PaymentID]
    H --> I[Customer pays on ARB page]
    I --> J[Merchant callback receives final response]
    J --> K{Has trandata?}
    K -- Yes --> L[Decrypt + normalize result]
    K -- No --> M[Fallback parse top-level fields]
    L --> N[Use helpers: isSuccess/isCaptured/...]
    M --> N
```

### 2) Webhook flow

```mermaid
flowchart TD
    A[ARB sends webhook] --> B[WebhookService.process]
    B --> C[Verify signature\nif secret configured]
    C --> D[Resolve payload/result nodes]
    D --> E{Status is success or pending?}
    E -- Yes --> F[Map normalized transaction data]
    F --> G[Run success callback]
    E -- No --> H[Map failure payload]
    H --> I[Run failure callback]
    G --> J[Return status=1 ack]
    I --> J
```

## Response handling behavior

- First priority is `trandata` (decrypt and parse it).
- If `trandata` is missing, fallback to top-level fields (`ErrorText`, `Error`, etc.).
- Accepts JSON arrays/objects and query-string-like payloads.
- Key names are normalized (`paymentId/paymentid`, `errorText/errortext`, `trackId/trackid`).

## UDF policy

- Supports `udf1` to `udf10` in request and normalized response.
- Each UDF must be scalar and max length 255.
- Auto defaults for missing `udf1..udf5` (when enabled):
  - `udf1 = order:{order_id|track_id}`
  - `udf2 = customer:{customer_id}`
  - `udf3 = channel:{channel}` (default `web`)
  - `udf4 = source:{source}` (default `bank_hosted`, `iframe` for iframe flow)
  - `udf5 = ref:{reference_type}` (default `TrackID`)
- Capture compliance:
  - For `action=5`, auto-set `udf7=R` (non-saved-card capture) when missing and enabled.
  - For `action=5`, `udf10` (if provided) must be `PARTIALCAPTURE` or `FINALCAPTURE`.

## Callback vs Webhook (recommended pattern)

- Always use webhook as the **single source of truth** for order/payment updates.
- Use `paymentCallback` for UX only (redirect page/message), not for final state changes.

## Handling callback result (UX only)

```php
$result = AlRajhiPayment::bankHosted()->handleResponse($request->all());
$service = AlRajhiPayment::bankHosted();

$service->isSuccess($result);
$service->isFailure($result);
$service->isCaptured($result);
$service->isAuthorized($result);
$service->isPending($result);
$service->isCancelled($result);
$service->isVoided($result);

$status = $service->getTransactionStatus($result);
$failure = $service->failureDetails($result);

// Recommended in callback: return message/view only
// Do NOT finalize order/payment status here.
```

## Update order and payment records (Webhook-driven)

Use your own models, and update states only inside webhook handlers.

```php
use AlRajhi\PaymentGateway\Facades\AlRajhiPayment;
AlRajhiPayment::webhook()->process(
    $payload,
    function (array $transactionData, string $type) {
        $order = \App\Models\Order::find($transactionData['track_id'] ?? null);

        if (! $order) {
            return;
        }

        // Idempotency: skip duplicates if already paid
        if ($order->status === 'paid') {
            return;
        }

        $order->payment->update([
            'payment_id' => $transactionData['payment_id'] ?? null,
            'transaction_id' => $transactionData['transaction_id'] ?? null,
            'status' => strtolower((string) ($transactionData['result'] ?? 'unknown')),
            'raw_response' => $transactionData,
        ]);

        switch (true) {
            case in_array(strtoupper((string) ($transactionData['result'] ?? '')), ['CAPTURED', 'SUCCESS', 'APPROVED'], true):
                $order->update(['status' => 'paid', 'paid_at' => now()]);
                break;

            case strtoupper((string) ($transactionData['result'] ?? '')) === 'PROCESSING':
                $order->update(['status' => 'payment_pending']);
                break;

            default:
                $order->update(['status' => 'payment_failed']);
                break;
        }
    },
    function (array $errorData, string $type) {
        $trackId = $errorData['transaction_data']['trackId'] ?? null;
        $order = $trackId ? \App\Models\Order::find($trackId) : null;

        if (! $order || $order->status === 'paid') {
            return;
        }

        $order->payment->update([
            'status' => 'failed',
            'error_code' => $errorData['error_code'] ?? null,
            'error_message' => $errorData['error_text'] ?? null,
            'raw_response' => $errorData,
        ]);

        $order->update(['status' => 'payment_failed']);
    }
);
```

## Webhook endpoint (primary)

The package registers:

```text
POST /alrajhi/webhook
```

Implementation example is already provided in:

- `Update order and payment records (Webhook-driven)`

## Callback endpoint (secondary / UX only)

Use callback endpoint to show user-facing result page or redirect logic only.
Do not make final order/payment updates here.

## BIN check

```php
$binData = AlRajhiPayment::binCheck('515735');
```

## Troubleshooting IPAY0100013 (Invalid transaction data)

When the gateway returns `IPAY0100013`, validate these items exactly:

- Endpoint URL must be your assigned gateway host (many merchants currently use `https://securepayments.neoleap.com.sa`).
- Required transaction fields must be present with exact meaning:
  - `id` (tranportal id)
  - `password` (tranportal password)
  - `action=1`
  - `currencyCode=682`
  - `amt` with 2 decimals (example `12.00`)
  - `trackId` unique value
  - `responseURL` and `errorURL` valid public HTTPS URLs
- Resource key must match the same environment/credentials pair.
- If you changed `.env`, run:

```bash
php artisan optimize:clear
```

This package now retries once automatically without URL-encoding `trandata` when the gateway returns `IPAY0100013`.
