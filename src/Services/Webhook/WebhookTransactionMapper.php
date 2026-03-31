<?php

namespace AlRajhi\PaymentGateway\Services\Webhook;

use AlRajhi\PaymentGateway\Contracts\ArrayValueResolverContract;
use AlRajhi\PaymentGateway\Support\TransactionStatusResolver;

class WebhookTransactionMapper
{
    public function __construct(
        protected ArrayValueResolverContract $valueResolver,
        protected TransactionStatusResolver $transactionStatusResolver
    ) {
    }

    public function isSuccessfulStatus(array $resultData): bool
    {
        $status = $this->valueResolver->first($resultData, ['status', 'result', 'paymentStatus']);
        $normalizedStatus = $this->transactionStatusResolver->normalize($status);

        if ($normalizedStatus === null) {
            return false;
        }

        $configuredSuccessfulStatuses = array_map(
            static fn (string $statusValue): string => strtoupper(trim($statusValue)),
            (array) config('alrajhi.response.success_statuses', ['1', 'approved', 'captured', 'processing', 'voided'])
        );

        return in_array($normalizedStatus, $configuredSuccessfulStatuses, true)
            || $this->transactionStatusResolver->isSuccessful($normalizedStatus)
            || $this->transactionStatusResolver->isPending($normalizedStatus);
    }

    public function mapToTransaction(array $payloadData, array $resultData): array
    {
        return [
            'payment_id' => $this->valueResolver->first($payloadData, ['paymentId', 'paymentid']),
            'transaction_id' => $this->valueResolver->first($payloadData, ['transId', 'transid']),
            'reference_number' => $this->valueResolver->first($payloadData, ['ref', 'referenceNo']),
            'track_id' => $this->valueResolver->first($payloadData, ['trackId', 'trackid']),
            'amount' => $this->valueResolver->first($payloadData, ['amt', 'amount']),
            'auth_code' => $this->valueResolver->first($payloadData, ['authCode', 'authcode']),
            'auth_response_code' => $this->valueResolver->first($payloadData, ['authRespCode', 'authrespcode']),
            'card_type' => $this->valueResolver->first($payloadData, ['cardType', 'cardtype']),
            'action_code' => $this->valueResolver->first($payloadData, ['actionCode', 'actioncode']),
            'card_number' => $this->valueResolver->first($payloadData, ['card', 'maskedCard']),
            'exp_month' => $this->valueResolver->first($payloadData, ['expMonth', 'expmonth']),
            'exp_year' => $this->valueResolver->first($payloadData, ['expYear', 'expyear']),
            'result' => $this->valueResolver->first($resultData, ['status', 'result', 'paymentStatus']),
            'timestamp' => $this->valueResolver->first($payloadData, ['paymentTimestamp', 'timestamp']) ?? now()->toIso8601String(),
            'raw_payload' => $payloadData,
            'raw_result' => $resultData,
        ];
    }

    public function mapToFailure(array $resultData, array $payloadData): array
    {
        return [
            'error_code' => $resultData['error'] ?? 'UNKNOWN_ERROR',
            'error_text' => $resultData['errorText'] ?? 'Unknown error',
            'transaction_data' => $payloadData,
        ];
    }
}
