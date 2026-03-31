<?php

namespace AlRajhi\PaymentGateway\Support;

class ApiResponse
{
    public static function success(array $data = [], int $status = 200): array
    {
        return [
            'success' => true,
            'status' => $status,
            'data' => $data,
        ];
    }

    public static function error(string $message, string $errorCode = 'IPAY000000', int $status = 422): array
    {
        return [
            'success' => false,
            'status' => $status,
            'error_code' => $errorCode,
            'message' => $message,
        ];
    }
}
