<?php

namespace AlRajhi\PaymentGateway\Contracts;

interface PaymentPayloadBuilderContract
{
    public function build(array $data): array;
}
