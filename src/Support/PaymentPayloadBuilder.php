<?php

namespace AlRajhi\PaymentGateway\Support;

use AlRajhi\PaymentGateway\Contracts\PaymentPayloadBuilderContract;
use Illuminate\Support\Str;

class PaymentPayloadBuilder implements PaymentPayloadBuilderContract
{
    public function build(array $data): array
    {
        $resolvedResponseUrl = $data['response_url'] ?? $data['responseURL'] ?? config('alrajhi.callbacks.response_url');
        $resolvedErrorUrl = $data['error_url'] ?? $data['errorURL'] ?? config('alrajhi.callbacks.error_url');
        $resolvedAmount = $data['amount'] ?? $data['amt'] ?? null;
        $resolvedAction = $data['action'] ?? '1';
        $resolvedCurrencyCode = $data['currency_code'] ?? $data['currencyCode'] ?? config('alrajhi.currency.default', '682');
        $resolvedTrackId = $data['track_id'] ?? $data['trackId'] ?? Str::uuid()->toString();
        $resolvedPortalId = $data['id'] ?? config('alrajhi.credentials.tranportal_id');
        $resolvedPortalPassword = $data['password'] ?? config('alrajhi.credentials.tranportal_password');

        $payload = [
            'id' => $resolvedPortalId,
            'trandata' => [
                'amt' => $resolvedAmount,
                'action' => $resolvedAction,
                'password' => $resolvedPortalPassword,
                'id' => $resolvedPortalId,
                'currencyCode' => $resolvedCurrencyCode,
                'trackId' => $resolvedTrackId,
            ],
        ];

        if (! empty($resolvedResponseUrl)) {
            $payload['responseURL'] = $resolvedResponseUrl;
            $payload['trandata']['responseURL'] = $resolvedResponseUrl;
        }

        if (! empty($resolvedErrorUrl)) {
            $payload['errorURL'] = $resolvedErrorUrl;
            $payload['trandata']['errorURL'] = $resolvedErrorUrl;
        }

        for ($index = 1; $index <= 10; $index++) {
            if (isset($data["udf{$index}"])) {
                $payload['trandata']["udf{$index}"] = $data["udf{$index}"];
            }
        }

        if (isset($data['langid'])) {
            $payload['trandata']['langid'] = $data['langid'];
        }

        if (isset($data['custid'])) {
            $payload['trandata']['custid'] = $data['custid'];
            $payload['trandata']['cust_cardHolderName'] = $data['cust_card_holder_name'] ?? null;
            $payload['trandata']['cust_mobile_number'] = $data['cust_mobile_number'] ?? null;
            $payload['trandata']['cust_emailId'] = $data['cust_email_id'] ?? null;
        }

        if (($data['is_iframe'] ?? false) === true) {
            $payload['trandata']['udf3'] = 'iframe';
        }

        if (isset($data['billing_details'])) {
            $payload['trandata']['billingDetails'] = $data['billing_details'];
        }

        if (isset($data['airline'])) {
            $payload['trandata']['airline'] = $data['airline'];
        }

        if (isset($data['account_details'])) {
            $payload['trandata']['accountDetails'] = $data['account_details'];
        }

        $fieldMap = [
            'trans_id' => 'transId',
            'transId' => 'transId',
            'card_on_file_token' => 'cardOnFileToken',
            'cardOnFileToken' => 'cardOnFileToken',
            'customer_id' => 'customerId',
            'customerId' => 'customerId',
            'trxn_type' => 'trxnType',
            'trxnType' => 'trxnType',
            'apple_pay' => 'applePay',
            'applePay' => 'applePay',
        ];

        foreach ($fieldMap as $inputKey => $gatewayKey) {
            if (array_key_exists($inputKey, $data) && $data[$inputKey] !== null) {
                $payload['trandata'][$gatewayKey] = $data[$inputKey];
            }
        }

        return $payload;
    }
}
