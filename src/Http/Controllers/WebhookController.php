<?php

namespace AlRajhi\PaymentGateway\Http\Controllers;

use AlRajhi\PaymentGateway\Facades\AlRajhiPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $decoded = json_decode($rawBody, true);
        $payload = is_array($decoded) ? $decoded : $request->all();

        try {
            $response = AlRajhiPayment::webhook()->process(
                $payload,
                function (array $transactionData, string $type): void {
                    Log::info('ARB webhook success callback', [
                        'type' => $type,
                        'track_id' => $transactionData['track_id'] ?? null,
                        'payment_id' => $transactionData['payment_id'] ?? null,
                    ]);
                },
                function (array $errorData, string $type): void {
                    Log::warning('ARB webhook failure callback', [
                        'type' => $type,
                        'error' => $errorData,
                    ]);
                }
            );

            return response()->json($response, 200);
        } catch (\Throwable $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json([
                [
                    'status' => '0',
                ],
            ], 500);
        }
    }
}
