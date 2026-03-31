<?php

namespace AlRajhi\PaymentGateway\Services\BankPayment;

use AlRajhi\PaymentGateway\Exceptions\ValidationException;

class RequestValidator
{
    public function validateRequiredFields(array $data): void
    {
        $required = ['amount', 'response_url', 'error_url'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Missing required field: {$field}");
            }
        }

        if (empty($data['customer_ip']) || ! filter_var($data['customer_ip'], FILTER_VALIDATE_IP)) {
            throw new ValidationException('Invalid customer IP address format');
        }

        if (! is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
            throw new ValidationException('Invalid amount: must be positive number');
        }

        $this->validateUdfFields($data);
    }

    protected function validateUdfFields(array $data): void
    {
        for ($index = 1; $index <= 10; $index++) {
            $key = "udf{$index}";

            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            if (is_array($data[$key]) || is_object($data[$key])) {
                throw new ValidationException("Invalid {$key}: must be scalar value");
            }

            if (mb_strlen((string) $data[$key]) > 255) {
                throw new ValidationException("Invalid {$key}: length must be less than or equal to 255");
            }
        }

        if ($this->isCaptureAction($data) && isset($data['udf10']) && trim((string) $data['udf10']) !== '') {
            $allowedCaptureTypes = ['PARTIALCAPTURE', 'FINALCAPTURE'];
            $normalizedUdf10 = strtoupper(trim((string) $data['udf10']));

            if (! in_array($normalizedUdf10, $allowedCaptureTypes, true)) {
                throw new ValidationException('Invalid udf10: allowed values for capture are PARTIALCAPTURE or FINALCAPTURE');
            }
        }
    }

    protected function isCaptureAction(array $data): bool
    {
        $action = trim((string) ($data['action'] ?? ''));

        return $action === '5';
    }
}
