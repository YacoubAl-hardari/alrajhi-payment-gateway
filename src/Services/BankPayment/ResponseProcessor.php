<?php

namespace AlRajhi\PaymentGateway\Services\BankPayment;

use AlRajhi\PaymentGateway\Contracts\ArrayValueResolverContract;
use AlRajhi\PaymentGateway\Exceptions\PaymentGatewayException;
use AlRajhi\PaymentGateway\Helpers\EncryptionHelper;
use AlRajhi\PaymentGateway\Support\PaymentResultHelper;

class ResponseProcessor
{
    protected const RESULT_KEYS = ['result', 'status'];

    protected const ERROR_TEXT_KEYS = ['errorText', 'errortext', 'message'];

    protected const ERROR_CODE_KEYS = ['error', 'Error', 'errorCode', 'errorcode'];

    public function __construct(
        protected EncryptionHelper $encryption,
        protected ArrayValueResolverContract $valueResolver
    ) {}

    public function handleResponseData(array $result): array
    {
        $decodedTrandata = $result['trandata_decoded'] ?? null;
        $data = is_array($decodedTrandata) ? $decodedTrandata : $result;
            $sensitiveFields = [
                'cardNo', 'cvv2', 'member', 'password'
            ];
            foreach ($sensitiveFields as $field) {
                if (isset($data[$field])) {
                    unset($data[$field]);
                }
            }

        $status = class_exists('PaymentResultHelper') ? PaymentResultHelper::extractUnifiedStatus($result) : [];

       $statusValue = $data['status'] ?? null;

       $hasError = false;
        foreach (['error', 'errorCode', 'error_code', 'errorText', 'error_text', 'message'] as $errField) {
            if (!empty($data[$errField])) {
                $hasError = true;
                break;
            }
        }
        
            $resultField = strtolower(trim((string)($data['result'] ?? '')));
            $successResults = ['1', 'success', 'approved', 'captured', 'processing', 'voided'];
            $isSuccess = in_array($resultField, array_map('strtolower', $successResults), true);

            $statusFinal = $status['status_final'] ?? 'unknown';
            $bankStatus  = $status['bank_status'] ?? null;
            $paymentStatus = match ($statusFinal) {
                'success'   => 'success',
                'failed'    => 'failed',
                'pending'   => 'pending',
                'voided'    => 'voided',
                'cancelled' => 'cancelled',
                default     => $bankStatus ?? 'unknown',
            };

        $arbFields = [
            'transId', 'date', 'trackId', 'udf1', 'udf2', 'udf3', 'udf4', 'udf5',
            'amt', 'authRespCode', 'authCode', 'cardType', 'custid', 'actionCode',
            'paymentId', 'ref', 'result', 'error', 'errorText', 'status',
        ];

        $arbData = [];
        foreach ($arbFields as $field) {
            // دعم camelCase وsnake_case
            $arbData[$field] = $data[$field] ?? $data[lcfirst($field)] ?? $data[ucfirst($field)] ?? null;
        }

        $response = array_merge(
            $status,
            $arbData,
            [
                'status'        => $statusValue,
                'is_success'    => $isSuccess,
                'is_failure'    => $hasError || (($status['status_final'] ?? null) === 'failed'),
                'is_pending'    => ($status['status_final'] ?? null) === 'pending',
                'is_captured'   => class_exists('PaymentResultHelper') ? PaymentResultHelper::isCaptured($result) : false,
                'is_authorized' => class_exists('PaymentResultHelper') ? PaymentResultHelper::isAuthorized($result) : false,
                'is_cancelled'  => class_exists('PaymentResultHelper') ? PaymentResultHelper::isCancelled($result) : false,
                'is_voided'     => class_exists('PaymentResultHelper') ? PaymentResultHelper::isVoided($result) : false,
                'error_code'    => $data['error'] ?? $data['errorCode'] ?? $data['error_code'] ?? null,
                'error_text'    => $data['errorText'] ?? $data['error_text'] ?? $data['message'] ?? null,
                'payment_id'    => $data['paymentId'] ?? $data['payment_id'] ?? null,
                'track_id'      => $data['trackId'] ?? $data['track_id'] ?? null,
                'amount'        => $data['amt'] ?? $data['amount'] ?? null,
                'card_type'     => $data['cardType'] ?? $data['card_type'] ?? null,
                'card'          => $data['card'] ?? null,
                'expMonth'      => $data['expMonth'] ?? null,
                'expYear'       => $data['expYear'] ?? null,
                'payment_status'=> $paymentStatus,
            ]
        );
        
        $duplicates = [
            'payment_id' => ['paymentId', 'payment_id'],
            'track_id'   => ['trackId', 'track_id'],
            'card_type'  => ['cardType', 'card_type'],
            'amount'     => ['amt', 'amount'],
            'error_code' => ['error', 'errorCode', 'error_code'],
            'error_text' => ['errorText', 'error_text', 'message'],
        ];
        foreach ($duplicates as $main => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $response) && $alias !== $main) {
                    unset($response[$alias]);
                }
            }
        }

        return $response;
    }

    public function handleResponse(array $gatewayResponseData): array
    {
        $encryptedTransactionData = $this->valueResolver->first($gatewayResponseData, ['trandata']);

        if ($this->valueResolver->isNotNullish($encryptedTransactionData)) {
            return $this->handleEncryptedResponse($gatewayResponseData, (string) $encryptedTransactionData);
        }

        return $this->handleNonEncryptedResponse($gatewayResponseData);
    }

    public function failureDetails(array $response): array
    {
        return [
            'error_code' => $this->valueResolver->first($response, ['error', 'error_code', 'errorCode']),
            'message' => $this->valueResolver->first($response, ['message', 'errorText', 'error_text']),
            'transaction_id' => $this->valueResolver->first($response, ['transaction_id', 'tranid', 'transId']),
            'payment_id' => $this->valueResolver->first($response, ['payment_id', 'paymentid', 'paymentId']),
            'track_id' => $this->valueResolver->first($response, ['track_id', 'trackId']),
            'result' => $this->valueResolver->first($response, ['result', 'status', 'payment_status']),
            'udf1' => $this->valueResolver->first($response, ['udf1']),
            'udf2' => $this->valueResolver->first($response, ['udf2']),
            'udf3' => $this->valueResolver->first($response, ['udf3']),
            'udf4' => $this->valueResolver->first($response, ['udf4']),
            'udf5' => $this->valueResolver->first($response, ['udf5']),
            'udf6' => $this->valueResolver->first($response, ['udf6']),
            'udf7' => $this->valueResolver->first($response, ['udf7']),
            'udf8' => $this->valueResolver->first($response, ['udf8']),
            'udf9' => $this->valueResolver->first($response, ['udf9']),
            'udf10' => $this->valueResolver->first($response, ['udf10']),
            'raw_data' => $response,
        ];
    }

    protected function handleEncryptedResponse(array $gatewayResponseData, string $encryptedTransactionData): array
    {
        $results = [];
        foreach ([null, true, false] as $tryUrlDecode) {
            try {
                $decryptedTransactionData = $this->encryption->decrypt($encryptedTransactionData, $tryUrlDecode);
                $transactionData = $this->decodeTransactionData($decryptedTransactionData);
                if (is_array($transactionData)) {
                    if (isset($transactionData[0]) && is_array($transactionData[0])) {
                        $mainData = $transactionData[0];
                    } else {
                        $mainData = $transactionData;
                    }
                    $nonNullFields = array_filter($mainData, fn($v) => $v !== null && $v !== '' && $v !== []);
                    if (count($nonNullFields) > 1) {

                        $payload = $this->buildSuccessPayload($mainData, $gatewayResponseData);
                        $payload['trandata_decoded'] = $mainData;
                        return $payload;
                    }
                    $results[] = ['try' => $tryUrlDecode, 'data' => $mainData];
                }
            } catch (\Throwable $e) {
                $results[] = ['try' => $tryUrlDecode, 'error' => $e->getMessage()];
            }
        }

        foreach ($results as $res) {
            if (isset($res['data']) && is_array($res['data'])) {
                $payload = $this->buildSuccessPayload($res['data'], $gatewayResponseData);
                $payload['trandata_decoded'] = $res['data'];
                return $payload;
            }
        }
        throw new PaymentGatewayException(
            'Invalid decrypted transaction data (all decode attempts)',
            'IPAY0100124',
            0,
            null,
            $this->buildErrorDetails($gatewayResponseData, null)
        );
    }

    protected function handleNonEncryptedResponse(array $gatewayResponseData): array
    {
        $fallbackErrorText = $this->valueResolver->first($gatewayResponseData, self::ERROR_TEXT_KEYS);
        $fallbackErrorCode = $this->valueResolver->first($gatewayResponseData, self::ERROR_CODE_KEYS);

        if ($this->valueResolver->isNotNullish($fallbackErrorText) || $this->valueResolver->isNotNullish($fallbackErrorCode)) {
            throw new PaymentGatewayException(
                (string) ($fallbackErrorText ?? 'Transaction failed'),
                (string) ($fallbackErrorCode ?? 'IPAY0100124'),
                0,
                null,
                $this->buildErrorDetails($gatewayResponseData, null)
            );
        }

        if (($this->toBool($_ENV['ALRAJHI_ACCEPT_DIRECT_CALLBACK_FIELDS'] ?? true)) === true) {
            $normalized = $this->buildDirectTransactionData($gatewayResponseData);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        throw new PaymentGatewayException(
            'Invalid payment response: missing trandata',
            'IPAY0100124',
            0,
            null,
            $this->buildErrorDetails($gatewayResponseData, null)
        );
    }

    protected function decodeTransactionData(string $decryptedTransactionData): ?array
    {
        $transactionData = json_decode($decryptedTransactionData, true);

        if (is_array($transactionData)) {
            return $transactionData;
        }

        parse_str($decryptedTransactionData, $transactionData);

        if (is_array($transactionData)) {
            return $transactionData;
        }

        return null;
    }

    protected function assertSuccessfulTransaction(array $gatewayResponseData, array $transactionData): void
    {
        $result = $this->valueResolver->first($transactionData, self::RESULT_KEYS);
        $errorTextInTrandata = $this->valueResolver->first($transactionData, ['errorText', 'errortext']);
        $errorCodeInTrandata = $this->valueResolver->first($transactionData, ['error', 'errorCode', 'errorcode']);

        if ($this->valueResolver->isNotNullish($result) && ! $this->isSuccessResult((string) $result)) {
            throw new PaymentGatewayException(
                (string) $result,
                (string) ($errorCodeInTrandata
                    ?? $this->valueResolver->first($gatewayResponseData, self::ERROR_CODE_KEYS)
                    ?? 'IPAY0100124'),
                0,
                null,
                $this->buildErrorDetails($gatewayResponseData, $transactionData)
            );
        }

        if (! $this->valueResolver->isNotNullish($result) && $this->valueResolver->isNotNullish($errorTextInTrandata)) {
            throw new PaymentGatewayException(
                (string) $errorTextInTrandata,
                (string) ($errorCodeInTrandata
                    ?? $this->valueResolver->first($gatewayResponseData, self::ERROR_CODE_KEYS)
                    ?? 'IPAY0100124'),
                0,
                null,
                $this->buildErrorDetails($gatewayResponseData, $transactionData)
            );
        }
    }

    protected function buildSuccessPayload(array $transactionData, array $gatewayResponseData = []): array
    {
        return array_merge(['success' => true], $transactionData);
    }

    protected function buildDirectTransactionData(array $data): ?array
    {
        $trackId = $this->valueResolver->first($data, ['trackId', 'trackid']);
        $result = $this->valueResolver->first($data, self::RESULT_KEYS);

        if (! $this->valueResolver->isNotNullish($trackId)) {
            return null;
        }

        if ($this->valueResolver->isNotNullish($result) && ! $this->isSuccessResult((string) $result)) {
            throw new PaymentGatewayException(
                (string) ($this->valueResolver->first($data, ['errorText', 'errortext', 'message']) ?? 'Transaction failed'),
                (string) ($this->valueResolver->first($data, ['error', 'errorCode', 'errorcode']) ?? 'IPAY0100124'),
                0,
                null,
                ['response' => $data]
            );
        }

        return $this->buildSuccessPayload($data);
    }

    protected function buildErrorDetails(array $topLevelData, ?array $transactionData): array
    {
        return [
            'transaction_id' => $this->valueResolver->first(
                $transactionData ?? $topLevelData,
                ['tranid', 'transId', 'transid', 'tranId']
            ),
            'payment_id' => $this->valueResolver->first(
                $topLevelData + ($transactionData ?? []),
                ['paymentid', 'paymentId', 'paymentID']
            ),
            'response' => $topLevelData,
            'trandata_payload' => $transactionData,
        ];
    }

    protected function isSuccessResult(string $status): bool
    {
        $normalizedStatus = strtolower(trim($status));
        $configuredStatuses = (string) ($_ENV['ALRAJHI_SUCCESS_STATUSES'] ?? '1,success,approved,captured,processing,voided');
        $configuredSuccessfulStatuses = array_map(
            static fn(string $statusValue): string => strtolower(trim($statusValue)),
            array_filter(array_map('trim', explode(',', $configuredStatuses)))
        );

        return in_array($normalizedStatus, $configuredSuccessfulStatuses, true);
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $result ?? false;
    }
}
