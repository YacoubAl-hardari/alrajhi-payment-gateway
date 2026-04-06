<?php

namespace AlRajhi\PaymentGateway\Http\Clients;

use AlRajhi\PaymentGateway\Contracts\ArrayValueResolverContract;
use AlRajhi\PaymentGateway\Contracts\GatewayBodyParserContract;
use AlRajhi\PaymentGateway\Contracts\PaymentPayloadBuilderContract;
use AlRajhi\PaymentGateway\Exceptions\PaymentGatewayException;
use AlRajhi\PaymentGateway\Helpers\EncryptionHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Arr;

class PaymentGatewayClient
{
    protected Client $httpClient;

    protected EncryptionHelper $encryption;

    protected PaymentPayloadBuilderContract $payloadBuilder;

    protected GatewayBodyParserContract $bodyParser;

    protected ArrayValueResolverContract $valueResolver;

    protected array $config;

    public function __construct(
        ?Client $httpClient = null,
        ?EncryptionHelper $encryption = null,
        ?PaymentPayloadBuilderContract $payloadBuilder = null,
        ?GatewayBodyParserContract $bodyParser = null,
        ?ArrayValueResolverContract $valueResolver = null
    ) {
        $configKey = implode('', ['al', 'rajhi']);
        $this->config = (array) config($configKey, []);
        $this->encryption = $encryption ?? new EncryptionHelper();
        $this->payloadBuilder = $payloadBuilder ?? app(PaymentPayloadBuilderContract::class);
        $this->bodyParser = $bodyParser ?? app(GatewayBodyParserContract::class);
        $this->valueResolver = $valueResolver ?? app(ArrayValueResolverContract::class);

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => 30,
            'verify' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    protected function getBaseUrl(): string
    {
        $env = $this->config['environment'] ?? 'sandbox';

        return (string) ($this->config['endpoints'][$env]['base_url'] ?? '');
    }

    public function generatePaymentToken(array $data, string $customerIp): array
    {
        try {
            $payload = $this->payloadBuilder->build($data);
            $plainTrandataArray = json_encode([$payload['trandata']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $plainTrandataObject = json_encode($payload['trandata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($plainTrandataArray === false || $plainTrandataObject === false) {
                throw new PaymentGatewayException('Failed to encode trandata payload', 'IPAY0100124');
            }

            try {
                return $this->submitHostedPaymentRequest($payload, $plainTrandataArray, $customerIp);
            } catch (PaymentGatewayException $exception) {
                if (! $this->isInvalidTransactionDataError($exception)) {
                    throw $exception;
                }

                if ($this->shouldRetryWithoutUrlEncoding($exception)) {
                    try {
                        return $this->submitHostedPaymentRequest($payload, $plainTrandataArray, $customerIp, false);
                    } catch (PaymentGatewayException $retryException) {
                        if (! $this->isInvalidTransactionDataError($retryException)) {
                            throw $retryException;
                        }
                    }
                }

                try {
                    return $this->submitHostedPaymentRequest($payload, $plainTrandataObject, $customerIp);
                } catch (PaymentGatewayException $objectException) {
                    if (! $this->isInvalidTransactionDataError($objectException)) {
                        throw $objectException;
                    }

                    if (! $this->shouldRetryWithoutUrlEncoding($objectException)) {
                        throw $objectException;
                    }

                    return $this->submitHostedPaymentRequest($payload, $plainTrandataObject, $customerIp, false);
                }
            }
        } catch (RequestException $e) {
            $rawErrorResponse = (string) ($e->getResponse()?->getBody()->getContents() ?? '');
            $parsedError = $this->bodyParser->parse($rawErrorResponse);
            $resolvedErrorCode = $this->valueResolver->first($parsedError, ['error', 'errorcode', 'code'])
                ?? 'IPAY0100160';
            $resolvedMessage = $this->valueResolver->first($parsedError, ['errorText', 'errortext', 'message'])
                ?? ('Failed to generate payment token: ' . $e->getMessage());

            throw new PaymentGatewayException(
                $resolvedMessage,
                (string) $resolvedErrorCode,
                (int) $e->getCode(),
                $e,
                [
                    'http_status' => $e->getResponse()?->getStatusCode(),
                    'response' => $parsedError,
                ]
            );
        }
    }

    protected function submitHostedPaymentRequest(
        array $payload,
        string $plainTrandata,
        string $customerIp,
        ?bool $urlEncodeBeforeEncrypt = null
    ): array {
        $encryptedTrandata = $this->encryption->encrypt($plainTrandata, $urlEncodeBeforeEncrypt);

        $requestBody = [
            'id' => $payload['id'],
            'trandata' => $encryptedTrandata,
        ];

        if (isset($payload['responseURL'])) {
            $requestBody['responseURL'] = $payload['responseURL'];
        }

        if (isset($payload['errorURL'])) {
            $requestBody['errorURL'] = $payload['errorURL'];
        }

        $serverIp = request()?->server('SERVER_ADDR');
        $headers = [
            'X-FORWARDED-FOR' => $serverIp ? ($customerIp . ',' . $serverIp) : $customerIp,
        ];
        $response = $this->httpClient->post(
            $this->config['endpoints'][$this->config['environment']]['payment_hosted']
                ?? $this->config['endpoints'][$this->config['environment']]['payment_token']
                ?? '',
            [
                'json' => [$requestBody],
                'headers' => $headers,
            ]
        );

        $rawBody = (string) $response->getBody()->getContents();
        $responseBody = $this->bodyParser->parse($rawBody);

        return $this->handleResponse($responseBody, $rawBody, $response->getStatusCode());
    }

    protected function shouldRetryWithoutUrlEncoding(PaymentGatewayException $exception): bool
    {
        if (! $this->toBool(config('alrajhi.encryption.retry_without_url_encoding_on_invalid_trandata', true))) {
            return false;
        }

        if (! $this->encryption->usesUrlEncodingBeforeEncrypt()) {
            return false;
        }

        return strtoupper($exception->getErrorCode()) === 'IPAY0100013';
    }

    protected function isInvalidTransactionDataError(PaymentGatewayException $exception): bool
    {
        return strtoupper($exception->getErrorCode()) === 'IPAY0100013';
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $normalized ?? false;
    }

    protected function handleResponse(array $response, string $rawBody = '', ?int $httpStatus = null): array
    {
        $entry = $this->resolvePrimaryEntry($response);
        $status = $this->valueResolver->first($entry, ['status', 'resultStatus', 'resultstatus']);

        if ($status === null && ($this->config['response']['strict_mode'] ?? false) === true) {
            throw new PaymentGatewayException(
                'Invalid response format from payment gateway',
                'IPAY0100124',
                0,
                null,
                [
                    'http_status' => $httpStatus,
                    'response' => $response,
                    'raw_body' => $rawBody,
                ]
            );
        }

        if ($this->isSuccessfulStatus($status)) {
            $resultValue = $this->valueResolver->first($entry, ['result', 'paymentURL', 'paymentUrl', 'url']);

            return [
                'success' => true,
                'payment_id' => $resultValue,
                'payment_url' => $resultValue,
                'error' => null,
                'error_text' => null,
                'status' => $status,
                'raw_response' => $entry,
            ];
        }

        $errorCode = $this->valueResolver->first($entry, ['error', 'errorCode', 'errorcode']) ?? 'IPAY0100124';
        $errorText = $this->valueResolver->first($entry, ['errorText', 'errortext', 'message']) ?? 'Unknown error from payment gateway';

        throw new PaymentGatewayException(
            (string) $errorText,
            (string) $errorCode,
            0,
            null,
            [
                'http_status' => $httpStatus,
                'response' => $entry,
                'raw_body' => $rawBody,
            ]
        );
    }

    public function binCheck(string $bin): array
    {
        try {
            $response = $this->httpClient->post(
                $this->config['endpoints'][$this->config['environment']]['bin_check'] ?? '',
                [
                    'json' => [
                        'bin' => $bin,
                        'id' => $this->config['credentials']['tranportal_id'] ?? null,
                        'password' => $this->config['credentials']['tranportal_password'] ?? null,
                    ],
                ]
            );

            $rawBody = (string) $response->getBody()->getContents();
            $decoded = $this->bodyParser->parse($rawBody);

            return [
                'success' => true,
                'data' => $decoded,
                'raw' => $rawBody,
            ];
        } catch (RequestException $e) {
            $rawErrorResponse = (string) ($e->getResponse()?->getBody()->getContents() ?? '');
            $parsedError = $this->bodyParser->parse($rawErrorResponse);

            throw new PaymentGatewayException(
                (string) ($this->valueResolver->first($parsedError, ['errorText', 'errortext', 'message'])
                    ?? ('BIN check failed: ' . $e->getMessage())),
                (string) ($this->valueResolver->first($parsedError, ['error', 'errorcode', 'code']) ?? 'IPAY0100381'),
                (int) $e->getCode(),
                $e,
                [
                    'http_status' => $e->getResponse()?->getStatusCode(),
                    'response' => $parsedError,
                ]
            );
        }
    }

    protected function resolvePrimaryEntry(array $response): array
    {
        if (Arr::isList($response) && isset($response[0]) && is_array($response[0])) {
            return $response[0];
        }

        return $response;
    }

    protected function isSuccessfulStatus(mixed $status): bool
    {
        if ($status === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $status));
        $allowed = array_map(
            static fn (string $entry): string => strtolower(trim($entry)),
            (array) ($this->config['response']['success_statuses'] ?? ['1'])
        );

        return in_array($normalized, $allowed, true);
    }
}
