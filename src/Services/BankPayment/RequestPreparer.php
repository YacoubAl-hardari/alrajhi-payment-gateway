<?php

namespace AlRajhi\PaymentGateway\Services\BankPayment;

use Illuminate\Support\Str;

class RequestPreparer
{
    public function prepare(array $paymentRequestData): array
    {
        $paymentRequestData = $this->normalizeAliases($paymentRequestData);
        $paymentRequestData = $this->resolveCallbackUrls($paymentRequestData);
        $paymentRequestData = $this->resolveCustomerIp($paymentRequestData);
        $paymentRequestData['track_id'] = $paymentRequestData['track_id'] ?? Str::uuid()->toString();

        return $this->resolveUdfDefaults($paymentRequestData);
    }

    protected function normalizeAliases(array $paymentRequestData): array
    {
        $aliases = [
            'amt' => 'amount',
            'trackId' => 'track_id',
            'currencyCode' => 'currency_code',
            'responseURL' => 'response_url',
            'errorURL' => 'error_url',
            'customerIp' => 'customer_ip',
        ];

        foreach ($aliases as $from => $to) {
            if (! array_key_exists($to, $paymentRequestData) && array_key_exists($from, $paymentRequestData)) {
                $paymentRequestData[$to] = $paymentRequestData[$from];
            }
        }

        return $paymentRequestData;
    }

    protected function resolveCallbackUrls(array $paymentRequestData): array
    {
        $paymentRequestData['response_url'] = $paymentRequestData['response_url']
            ?? ($_ENV['ALRAJHI_RESPONSE_URL'] ?? null);
        $paymentRequestData['error_url'] = $paymentRequestData['error_url']
            ?? ($_ENV['ALRAJHI_ERROR_URL'] ?? null);

        return $paymentRequestData;
    }

    protected function resolveCustomerIp(array $paymentRequestData): array
    {
        if (! empty($paymentRequestData['customer_ip'])) {
            return $paymentRequestData;
        }

        $request = request();

        $headerForwardedFor = $request?->header('x-forwarded-for');
        if (is_string($headerForwardedFor) && $headerForwardedFor !== '') {
            $firstForwardedIp = trim(explode(',', $headerForwardedFor)[0]);
            if ($firstForwardedIp !== '') {
                $paymentRequestData['customer_ip'] = $firstForwardedIp;

                return $paymentRequestData;
            }
        }

        $requestIp = $request?->ip();
        if (is_string($requestIp) && $requestIp !== '') {
            $paymentRequestData['customer_ip'] = $requestIp;

            return $paymentRequestData;
        }

        $remoteAddress = $request?->server('REMOTE_ADDR');
        if (is_string($remoteAddress) && $remoteAddress !== '') {
            $paymentRequestData['customer_ip'] = $remoteAddress;
        }

        return $paymentRequestData;
    }

    protected function resolveUdfDefaults(array $paymentRequestData): array
    {
        if (! $this->isUdfAutoFillEnabled()) {
            $this->applyCaptureUdfDefaults($paymentRequestData);

            return $paymentRequestData;
        }

        $orderIdentifier = $paymentRequestData['order_id'] ?? $paymentRequestData['track_id'] ?? null;
        $customerIdentifier = $paymentRequestData['customer_id'] ?? null;
        $channel = $paymentRequestData['channel'] ?? 'web';
        $source = $paymentRequestData['source'] ?? (($paymentRequestData['is_iframe'] ?? false) ? 'iframe' : 'bank_hosted');
        $referenceType = $paymentRequestData['reference_type'] ?? 'TrackID';

        $defaults = [
            'udf1' => $orderIdentifier !== null ? 'order:' . (string) $orderIdentifier : null,
            'udf2' => $customerIdentifier !== null ? 'customer:' . (string) $customerIdentifier : null,
            'udf3' => 'channel:' . (string) $channel,
            'udf4' => 'source:' . (string) $source,
            'udf5' => 'ref:' . (string) $referenceType,
        ];

        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $paymentRequestData) || $paymentRequestData[$key] === null || $paymentRequestData[$key] === '') {
                if ($value !== null && $value !== '') {
                    $paymentRequestData[$key] = $value;
                }
            }
        }

        $this->applyCaptureUdfDefaults($paymentRequestData);

        return $paymentRequestData;
    }

    protected function applyCaptureUdfDefaults(array &$paymentRequestData): void
    {
        if (! $this->isCaptureUdf7AutoSetEnabled()) {
            return;
        }

        if (! $this->isCaptureAction($paymentRequestData)) {
            return;
        }

        if (! isset($paymentRequestData['cardOnFileToken'])
            && (! isset($paymentRequestData['udf7']) || trim((string) $paymentRequestData['udf7']) === '')
        ) {
            $paymentRequestData['udf7'] = 'R';
        }
    }

    protected function isUdfAutoFillEnabled(): bool
    {
        return $this->toBool($_ENV['ALRAJHI_UDF_AUTO_FILL_DEFAULTS'] ?? true);
    }

    protected function isCaptureUdf7AutoSetEnabled(): bool
    {
        return $this->toBool($_ENV['ALRAJHI_CAPTURE_AUTO_SET_UDF7_R'] ?? true);
    }

    protected function isCaptureAction(array $data): bool
    {
        $action = trim((string) ($data['action'] ?? ''));

        return $action === '5';
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $result ?? false;
    }
}
