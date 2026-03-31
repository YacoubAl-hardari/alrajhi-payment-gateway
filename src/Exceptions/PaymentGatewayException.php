<?php

namespace AlRajhi\PaymentGateway\Exceptions;

use AlRajhi\PaymentGateway\Support\ArbErrorCatalog;
use Exception;

class PaymentGatewayException extends Exception
{
    protected string $errorCode;

    protected array $details;

    public function __construct(
        string $message = '',
        string $errorCode = 'IPAY000000',
        int $code = 0,
        ?Exception $previous = null,
        array $details = []
    ) {
        $normalizedCode = ArbErrorCatalog::normalizeCode($errorCode)
            ?? ArbErrorCatalog::normalizeCode($message)
            ?? 'IPAY000000';

        $officialMessage = ArbErrorCatalog::messageFor($normalizedCode);
        $resolvedMessage = $this->resolveMessage($message, $officialMessage, $normalizedCode);

        if ($officialMessage !== null && (bool) config('alrajhi.errors.include_official_message', true)) {
            $details['official_message'] = $officialMessage;
        }

        parent::__construct($resolvedMessage, $code, $previous);

        $this->errorCode = $normalizedCode;
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function toArray(): array
    {
        return [
            'success' => false,
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'details' => $this->details,
        ];
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    private function resolveMessage(string $message, ?string $officialMessage, string $errorCode): string
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return $officialMessage ?? 'Payment gateway error.';
        }

        $preferCatalogMessage = (bool) config('alrajhi.errors.prefer_catalog_message', true);
        if ($preferCatalogMessage && $officialMessage !== null) {
            $genericMessages = [
                'unknown error from payment gateway',
                'transaction failed',
                'payment gateway error',
            ];

            if (in_array(strtolower($trimmed), $genericMessages, true)) {
                return $officialMessage;
            }

            if (str_contains(strtoupper($trimmed), $errorCode)) {
                return $officialMessage;
            }
        }

        return $trimmed;
    }
}
