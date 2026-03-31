<?php

namespace AlRajhi\PaymentGateway\Services\BankPayment;

use AlRajhi\PaymentGateway\Contracts\ArrayValueResolverContract;
use AlRajhi\PaymentGateway\Exceptions\PaymentGatewayException;
use AlRajhi\PaymentGateway\Helpers\EncryptionHelper;

class ResponseProcessor
{
    protected const RESULT_KEYS = ['result', 'status'];

    protected const ERROR_TEXT_KEYS = ['errorText', 'errortext', 'message'];

    protected const ERROR_CODE_KEYS = ['error', 'Error', 'errorCode', 'errorcode'];

    public function __construct(
        protected EncryptionHelper $encryption,
        protected ArrayValueResolverContract $valueResolver
    ) {
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
        $decryptedTransactionData = $this->encryption->decrypt($encryptedTransactionData);
        $transactionData = $this->decodeTransactionData($decryptedTransactionData);

        if (! is_array($transactionData)) {
            throw new PaymentGatewayException(
                'Invalid decrypted transaction data',
                'IPAY0100124',
                0,
                null,
                $this->buildErrorDetails($gatewayResponseData, null)
            );
        }

        $this->assertSuccessfulTransaction($gatewayResponseData, $transactionData);

        return $this->buildSuccessPayload($transactionData, $gatewayResponseData);
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
        $result = $this->valueResolver->first($transactionData, self::RESULT_KEYS);

        return [
            'success' => true,
            'payment_id' => $this->valueResolver->first($gatewayResponseData, ['paymentId', 'paymentid'])
                ?? $this->valueResolver->first($transactionData, ['paymentId', 'paymentid']),
            'result' => $result,
            'transaction_id' => $this->valueResolver->first($transactionData, ['transId', 'transid', 'tranid']),
            'reference_number' => $this->valueResolver->first($transactionData, ['ref', 'referenceNo']),
            'track_id' => $this->valueResolver->first($transactionData, ['trackId', 'trackid']),
            'amount' => $this->valueResolver->first($transactionData, ['amt', 'amount']),
            'auth_code' => $this->valueResolver->first($transactionData, ['authCode', 'authcode']),
            'auth_response_code' => $this->valueResolver->first($transactionData, ['authRespCode', 'authrespcode']),
            'card_type' => $this->valueResolver->first($transactionData, ['cardType', 'cardtype']),
            'action_code' => $this->valueResolver->first($transactionData, ['actionCode', 'actioncode']),
            'card_number' => $this->valueResolver->first($transactionData, ['card', 'maskedCard']),
            'exp_month' => $this->valueResolver->first($transactionData, ['expMonth', 'expmonth']),
            'exp_year' => $this->valueResolver->first($transactionData, ['expYear', 'expyear']),
            'udf1' => $this->valueResolver->first($transactionData, ['udf1']),
            'udf2' => $this->valueResolver->first($transactionData, ['udf2']),
            'udf3' => $this->valueResolver->first($transactionData, ['udf3']),
            'udf4' => $this->valueResolver->first($transactionData, ['udf4']),
            'udf5' => $this->valueResolver->first($transactionData, ['udf5']),
            'udf6' => $this->valueResolver->first($transactionData, ['udf6']),
            'udf7' => $this->valueResolver->first($transactionData, ['udf7']),
            'udf8' => $this->valueResolver->first($transactionData, ['udf8']),
            'udf9' => $this->valueResolver->first($transactionData, ['udf9']),
            'udf10' => $this->valueResolver->first($transactionData, ['udf10']),
            'raw_data' => $transactionData,
        ];
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
            static fn (string $statusValue): string => strtolower(trim($statusValue)),
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
