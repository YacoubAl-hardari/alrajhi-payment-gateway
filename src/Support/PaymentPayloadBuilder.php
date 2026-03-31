<?php

namespace AlRajhi\PaymentGateway\Support;

use AlRajhi\PaymentGateway\Contracts\PaymentPayloadBuilderContract;
use Illuminate\Support\Str;

class PaymentPayloadBuilder implements PaymentPayloadBuilderContract
{
    public function build(array $data): array
    {
        $resolvedResponseUrl = $data['response_url'] ?? config('alrajhi.callbacks.response_url');
        $resolvedErrorUrl = $data['error_url'] ?? config('alrajhi.callbacks.error_url');

        $payload = [
            'id' => config('alrajhi.credentials.tranportal_id'),
            'trandata' => [
                'amt' => $data['amount'],
                'action' => $data['action'] ?? '1',
                'password' => config('alrajhi.credentials.tranportal_password'),
                'id' => config('alrajhi.credentials.tranportal_id'),
                'currencyCode' => $data['currency_code'] ?? config('alrajhi.currency.default', '682'),
                'trackId' => $data['track_id'] ?? Str::uuid()->toString(),
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
