<?php

namespace AlRajhi\PaymentGateway\Exceptions;

class EncryptionException extends PaymentGatewayException
{
    public function __construct(string $message = 'Encryption/Decryption error', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'IPAY0100264', $code, $previous instanceof \Exception ? $previous : null);
    }
}
