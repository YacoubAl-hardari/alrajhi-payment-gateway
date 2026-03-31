<?php

namespace AlRajhi\PaymentGateway;

use AlRajhi\PaymentGateway\Http\Clients\PaymentGatewayClient;
use AlRajhi\PaymentGateway\Services\ApplePayService;
use AlRajhi\PaymentGateway\Services\BankHostedService;
use AlRajhi\PaymentGateway\Services\FasterCheckoutService;
use AlRajhi\PaymentGateway\Services\IframeService;
use AlRajhi\PaymentGateway\Services\WebhookService;

class AlRajhiPaymentManager
{
    protected PaymentGatewayClient $client;

    protected array $services = [];

    public function __construct(PaymentGatewayClient $client)
    {
        $this->client = $client;
    }

    public function bankHosted(): BankHostedService
    {
        if (! isset($this->services['bank_hosted'])) {
            $this->services['bank_hosted'] = app(BankHostedService::class);
        }

        return $this->services['bank_hosted'];
    }

    public function fasterCheckout(): FasterCheckoutService
    {
        if (! isset($this->services['faster_checkout'])) {
            $this->services['faster_checkout'] = app(FasterCheckoutService::class);
        }

        return $this->services['faster_checkout'];
    }

    public function iframe(): IframeService
    {
        if (! isset($this->services['iframe'])) {
            $this->services['iframe'] = app(IframeService::class);
        }

        return $this->services['iframe'];
    }

    public function applePay(): ApplePayService
    {
        if (! isset($this->services['apple_pay'])) {
            $this->services['apple_pay'] = app(ApplePayService::class);
        }

        return $this->services['apple_pay'];
    }

    public function webhook(): WebhookService
    {
        if (! isset($this->services['webhook'])) {
            $this->services['webhook'] = app(WebhookService::class);
        }

        return $this->services['webhook'];
    }

    public function binCheck(string $bin): array
    {
        return $this->client->binCheck($bin);
    }
}
