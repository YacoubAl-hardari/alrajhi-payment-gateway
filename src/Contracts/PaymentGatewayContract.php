<?php

namespace AlRajhi\PaymentGateway\Contracts;

interface PaymentGatewayContract
{
    public function initiate(array $data): array;

    public function handleResponse(array $data): array;
}
