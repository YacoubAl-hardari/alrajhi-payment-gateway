<?php

namespace AlRajhi\PaymentGateway\Services\Webhook;

class WebhookPayloadResolver
{
    public function extract(array $webhookPayload): array
    {
        return [
            'notification_type' => $webhookPayload['type'] ?? 'PAYMENT',
            'result_data' => $this->resolveNode($webhookPayload, ['result', 'Result']),
            'payload_data' => $this->resolveNode($webhookPayload, ['payLoad', 'payload', 'PayLoad']),
        ];
    }

    protected function resolveNode(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $node = $payload[$key];

            if (is_array($node) && isset($node[0]) && is_array($node[0])) {
                return $node[0];
            }

            if (is_array($node)) {
                return $node;
            }
        }

        return [];
    }
}
