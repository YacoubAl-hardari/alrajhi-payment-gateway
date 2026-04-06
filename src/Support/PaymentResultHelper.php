<?php

namespace AlRajhi\PaymentGateway\Support;

class PaymentResultHelper
{

    /**
     * Unified status extraction for payment result.
     * Returns array: [status_final, system_status, bank_status]
     * status_final: success | failed | pending | unknown
     * system_status: success | failed | pending
     * bank_status: raw status from bank
     */
    public static function extractUnifiedStatus(array $result): array
    {
        $data = isset($result['trandata_decoded']) && is_array($result['trandata_decoded']) ? $result['trandata_decoded'] : $result;
        $error = $data['error'] ?? $data['errorCode'] ?? $data['error_code'] ?? null;
        $errorText = $data['errorText'] ?? $data['error_text'] ?? $data['message'] ?? null;
        $status = $data['status'] ?? null;
        $resultField = $data['result'] ?? null;
        $actionCode = $data['actionCode'] ?? $data['action_code'] ?? null;
        $bankStatus = $resultField ?? $actionCode ?? $status;

        // منطق الدليل الرسمي
        if (!empty($error) || !empty($errorText) || $status === '2') {
            $statusFinal = 'failed';
            $systemStatus = 'failed';
        } elseif ($status === '1' && !empty($resultField)) {
            $statusFinal = 'success';
            $systemStatus = 'success';
        } elseif (strtolower((string)$resultField) === 'processing' || strtolower((string)$status) === 'processing') {
            $statusFinal = 'pending';
            $systemStatus = 'pending';
        } else {
            $statusFinal = 'unknown';
            $systemStatus = 'pending';
        }

        return [
            'status_final' => $statusFinal,
            'system_status' => $systemStatus,
            'bank_status' => $bankStatus,
        ];
    }


    protected static function getField($result, $key)
    {
        if (isset($result[$key])) {
            return $result[$key];
        }
        if (isset($result['trandata_decoded']) && is_array($result['trandata_decoded']) && isset($result['trandata_decoded'][$key])) {
            return $result['trandata_decoded'][$key];
        }
        return null;
    }

    public static function isSuccess(array $result): bool
    {
        $val = self::getField($result, 'result');
        if ($val === null) {
            $val = self::getField($result, 'actionCode'); // بعض البنوك تستخدم actionCode
        }
        return in_array(strtolower((string)$val), [
            '1','success','approved','captured','processing','voided',
        ]);
    }

    public static function isFailure(array $result): bool
    {
        $errorFields = [
            'error', 'errorCode', 'error_code', 'errorText', 'error_text', 'message'
        ];
        foreach ($errorFields as $field) {
            $val = self::getField($result, $field);
            if ($val !== null && $val !== '') {
                return true;
            }
        }
        return false;
    }

    public static function isCaptured(array $result): bool
    {
        $val = self::getField($result, 'action_code');
        if ($val === null) {
            $val = self::getField($result, 'actionCode');
        }
        return in_array((string)$val, ['1','captured']);
    }

    public static function isAuthorized(array $result): bool
    {
        $val = self::getField($result, 'action_code');
        if ($val === null) {
            $val = self::getField($result, 'actionCode');
        }
        return in_array((string)$val, ['0','authorized']);
    }

    public static function isPending(array $result): bool
    {
        $val = self::getField($result, 'result');
        return strtolower((string)$val) === 'processing';
    }

    public static function isCancelled(array $result): bool
    {
        $val = self::getField($result, 'result');
        return strtolower((string)$val) === 'cancelled';
    }

    public static function isVoided(array $result): bool
    {
        $val = self::getField($result, 'result');
        return strtolower((string)$val) === 'voided';
    }
}
