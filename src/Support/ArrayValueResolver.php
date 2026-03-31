<?php

namespace AlRajhi\PaymentGateway\Support;

use AlRajhi\PaymentGateway\Contracts\ArrayValueResolverContract;

class ArrayValueResolver implements ArrayValueResolverContract
{
    public function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $this->isNotNullish($data[$key])) {
                return $data[$key];
            }

            $lower = strtolower($key);
            foreach ($data as $entryKey => $value) {
                if (strtolower((string) $entryKey) === $lower && $this->isNotNullish($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    public function isNotNullish(mixed $value): bool
    {
        return ! in_array($value, [null, '', 'null', 'NULL'], true);
    }
}
