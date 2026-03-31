<?php

namespace AlRajhi\PaymentGateway\Services;

use AlRajhi\PaymentGateway\Contracts\ArrayValueResolverContract;
use AlRajhi\PaymentGateway\Contracts\PaymentGatewayContract;
use AlRajhi\PaymentGateway\Http\Clients\PaymentGatewayClient;
use AlRajhi\PaymentGateway\Services\BankPayment\RequestPreparer;
use AlRajhi\PaymentGateway\Services\BankPayment\RequestValidator;
use AlRajhi\PaymentGateway\Services\BankPayment\ResponseProcessor;
use AlRajhi\PaymentGateway\Support\TransactionStatusResolver;

class BankHostedService implements PaymentGatewayContract
{
    public function __construct(
        protected PaymentGatewayClient $client,
        protected RequestPreparer $requestPreparer,
        protected RequestValidator $requestValidator,
        protected ResponseProcessor $responseProcessor,
        protected ArrayValueResolverContract $valueResolver,
        protected TransactionStatusResolver $transactionStatusResolver
    ) {
    }

    public function initiate(array $paymentRequestData): array
    {
        $paymentRequestData = $this->requestPreparer->prepare($paymentRequestData);
        $this->requestValidator->validateRequiredFields($paymentRequestData);

        $gatewayInitiationResponse = $this->client->generatePaymentToken(
            $paymentRequestData,
            $paymentRequestData['customer_ip']
        );

        if ($gatewayInitiationResponse['success']) {
            $resultParts = explode(':', (string) $gatewayInitiationResponse['payment_url'], 2);
            $paymentId = $resultParts[0] ?? null;
            $paymentGatewayUrl = $resultParts[1] ?? null;
            $redirectUrl = $this->buildRedirectUrl($paymentId, $paymentGatewayUrl);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'payment_url' => $paymentGatewayUrl,
                'redirect_url' => $redirectUrl,
                'track_id' => $paymentRequestData['track_id'],
            ];
        }

        return $gatewayInitiationResponse;
    }

    public function handleResponse(array $gatewayResponseData): array
    {
        return $this->responseProcessor->handleResponse($gatewayResponseData);
    }

    public function getTransactionStatus(array $response): ?string
    {
        $rawStatus = $this->valueResolver->first($response, ['result', 'status', 'payment_status']);

        if (! $this->valueResolver->isNotNullish($rawStatus)) {
            return null;
        }

        return $this->transactionStatusResolver->normalize($rawStatus);
    }

    public function isSuccess(array $response): bool
    {
        $status = $this->getTransactionStatus($response);

        if ($status === null) {
            return false;
        }

        return $this->transactionStatusResolver->isSuccessful($status);
    }

    public function isFailure(array $response): bool
    {
        if ($this->valueResolver->isNotNullish($this->valueResolver->first($response, ['error', 'error_code']))) {
            return true;
        }

        $status = $this->getTransactionStatus($response);

        if ($status === null) {
            return false;
        }

        return $this->transactionStatusResolver->isFailure($status);
    }

    public function isCancelled(array $response): bool
    {
        $status = $this->getTransactionStatus($response);

        if ($status === null) {
            return false;
        }

        return $this->transactionStatusResolver->isCancelled($status);
    }

    public function isCaptured(array $response): bool
    {
        return $this->transactionStatusResolver->isCaptured($this->getTransactionStatus($response));
    }

    public function isAuthorized(array $response): bool
    {
        $status = $this->getTransactionStatus($response);

        return $this->transactionStatusResolver->isAuthorized($status);
    }

    public function isPending(array $response): bool
    {
        $status = $this->getTransactionStatus($response);

        return $this->transactionStatusResolver->isPending($status);
    }

    public function isVoided(array $response): bool
    {
        return $this->transactionStatusResolver->isVoided($this->getTransactionStatus($response));
    }

    public function failureDetails(array $response): array
    {
        $details = $this->responseProcessor->failureDetails($response);

        $details['result'] = $this->getTransactionStatus($response);

        return $details;
    }

    protected function buildRedirectUrl(?string $paymentId, ?string $baseUrl): ?string
    {
        if (! $paymentId || ! $baseUrl) {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        if (! str_contains($baseUrl, 'PaymentID=')) {
            return $baseUrl . $separator . 'PaymentID=' . urlencode($paymentId);
        }

        return $baseUrl;
    }
}
