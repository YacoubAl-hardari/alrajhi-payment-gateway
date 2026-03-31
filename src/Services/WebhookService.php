<?php

namespace AlRajhi\PaymentGateway\Services;

use AlRajhi\PaymentGateway\Contracts\WebhookHandlerContract;
use AlRajhi\PaymentGateway\Exceptions\PaymentGatewayException;
use AlRajhi\PaymentGateway\Services\Webhook\WebhookPayloadResolver;
use AlRajhi\PaymentGateway\Services\Webhook\WebhookSignatureVerifier;
use AlRajhi\PaymentGateway\Services\Webhook\WebhookTransactionMapper;
use Illuminate\Support\Facades\Log;

class WebhookService implements WebhookHandlerContract
{
    public function __construct(
        private readonly WebhookSignatureVerifier $signatureVerifier,
        private readonly WebhookPayloadResolver $payloadResolver,
        private readonly WebhookTransactionMapper $transactionMapper
    ) {
    }

    public function process(
        array $webhookPayload,
        callable $successCallback,
        callable $failureCallback
    ): array {
        Log::info('Webhook received from ARB', [
            'type' => $webhookPayload['type'] ?? 'unknown',
            'ip' => request()->ip(),
        ]);

        try {
            $this->signatureVerifier->verify($webhookPayload);

            $resolvedPayload = $this->payloadResolver->extract($webhookPayload);
            $notificationType = $resolvedPayload['notification_type'];
            $resultData = $resolvedPayload['result_data'];
            $payloadData = $resolvedPayload['payload_data'];

            $isSuccess = $this->transactionMapper->isSuccessfulStatus($resultData);

            if ($isSuccess) {
                $transactionData = $this->transactionMapper->mapToTransaction($payloadData, $resultData);
                $successCallback($transactionData, $notificationType);

                return ['status' => '1'];
            }

            $errorData = $this->transactionMapper->mapToFailure($resultData, $payloadData);

            $failureCallback($errorData, $notificationType);

            return ['status' => '1'];
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $webhookPayload,
            ]);

            throw new PaymentGatewayException('Webhook processing failed: ' . $e->getMessage(), 'IPAY0100124');
        }
    }
}
