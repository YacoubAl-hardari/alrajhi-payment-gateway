<?php

namespace AlRajhi\PaymentGateway\Services\Webhook;

use AlRajhi\PaymentGateway\Exceptions\PaymentGatewayException;

class WebhookSignatureVerifier
{
    public function verify(array $payload): void
    {
        $secret = config('alrajhi.webhook.secret');

        if (! $secret) {
            return;
        }

        $signature = (string) ($payload['signature']
            ?? request()?->header('x-signature')
            ?? request()?->header('x-webhook-signature')
            ?? '');

        if ($signature === '') {
            return;
        }

        $payloadForHash = $payload;
        unset($payloadForHash['signature']);

        $computedSignature = hash_hmac(
            'sha256',
            (string) json_encode($payloadForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) $secret
        );

        if (! hash_equals($computedSignature, $signature)) {
            throw new PaymentGatewayException('Invalid webhook signature', 'IPAY0100264');
        }
    }
}
