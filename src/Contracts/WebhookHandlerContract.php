<?php

namespace AlRajhi\PaymentGateway\Contracts;

interface WebhookHandlerContract
{
    public function process(array $payload, callable $successCallback, callable $failureCallback): array;
}
