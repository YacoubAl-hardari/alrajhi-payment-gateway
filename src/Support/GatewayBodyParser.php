<?php

namespace AlRajhi\PaymentGateway\Support;

use AlRajhi\PaymentGateway\Contracts\GatewayBodyParserContract;

class GatewayBodyParser implements GatewayBodyParserContract
{
    public function parse(string $rawBody): array
    {
        $trimmed = trim($rawBody);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if ((bool) config('alrajhi.response.accept_plain_query_response', true)) {
            parse_str($trimmed, $queryData);
            if (is_array($queryData) && $queryData !== []) {
                return $queryData;
            }
        }

        return ['raw' => $trimmed];
    }
}
