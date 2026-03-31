<?php

namespace AlRajhi\PaymentGateway\Exceptions;

class ValidationException extends PaymentGatewayException
{
    public function __construct(string $message = 'Validation error', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'IPAY0100124', $code, $previous instanceof \Exception ? $previous : null);
    }
}
