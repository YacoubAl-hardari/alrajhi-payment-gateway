<?php

namespace AlRajhi\PaymentGateway\Services;

use AlRajhi\PaymentGateway\Exceptions\ValidationException;

class FasterCheckoutService extends BankHostedService
{
    public function initiate(array $data): array
    {
        if (empty($data['custid'])) {
            throw new ValidationException('Customer ID (custid) is required for Faster Checkout');
        }

        $data['udf4'] = $data['udf4'] ?? 'FC';

        return parent::initiate($data);
    }
}
