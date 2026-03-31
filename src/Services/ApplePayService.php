<?php

namespace AlRajhi\PaymentGateway\Services;

class ApplePayService extends BankHostedService
{
    public function initiate(array $data): array
    {
        return parent::initiate($data);
    }
}
