<?php

namespace AlRajhi\PaymentGateway\Contracts;

interface ArrayValueResolverContract
{
    public function first(array $data, array $keys): mixed;

    public function isNotNullish(mixed $value): bool;
}
