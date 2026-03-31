<?php

namespace AlRajhi\PaymentGateway\Contracts;

interface GatewayBodyParserContract
{
    public function parse(string $rawBody): array;
}
