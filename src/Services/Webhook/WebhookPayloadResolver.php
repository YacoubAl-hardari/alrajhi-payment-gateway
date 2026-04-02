<?php

namespace AlRajhi\PaymentGateway\Services\Webhook;

class WebhookPayloadResolver
{
    public function extract(array $webhookPayload): array
    {
        $normalizedPayload = $this->normalizeRoot($webhookPayload);

        return [
            'notification_type' => $normalizedPayload['type'] ?? 'PAYMENT',
            'result_data' => $this->resolveNode($normalizedPayload, ['result', 'Result']),
            'payload_data' => $this->resolveNode($normalizedPayload, ['payLoad', 'payload', 'PayLoad']),
        ];
    }

    protected function normalizeRoot(array $payload): array
    {
        if (isset($payload[0]) && is_array($payload[0])) {
            return $payload[0];
        }

        return $payload;
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

            if (is_string($node)) {
                $decoded = $this->decodeJsonNode($node);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    protected function decodeJsonNode(string $node): ?array
    {
        $trimmed = trim($node);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            $unescaped = stripcslashes($trimmed);
            $decoded = json_decode($unescaped, true);
        }

        if (! is_array($decoded)) {
            return null;
        }

        if (isset($decoded[0]) && is_array($decoded[0])) {
            return $decoded[0];
        }

        return $decoded;
    }
}
