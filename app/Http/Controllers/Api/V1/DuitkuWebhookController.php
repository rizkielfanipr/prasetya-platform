<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Payments\Actions\ProcessGatewayNotification;
use App\Domain\Payments\Data\InboundNotification;
use App\Domain\Payments\Enums\WebhookStatus;
use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DuitkuWebhookController
{
    private const int MAX_BODY_BYTES = 65536;

    /**
     * Duitku retries until it gets a 200, up to 5 times. A 500 is how an unconfirmed success asks for that retry.
     * The verifier reads the raw body, so the signature is checked over exactly what was received.
     */
    public function __invoke(Request $request, ProcessGatewayNotification $process): JsonResponse
    {
        $rawBody = $request->getContent();

        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            return ApiResponse::error(ApiErrorCode::WebhookRejected, 'Notifikasi ditolak.', 413);
        }

        $reference = $request->input('reference');

        $event = $process(new InboundNotification(
            requestId: is_string($reference) ? $reference : null,
            rawBody: $rawBody,
            sourceIp: $request->ip(),
        ));

        if (! $event->signature_valid) {
            return ApiResponse::error(ApiErrorCode::WebhookRejected, 'Notifikasi ditolak.', 401);
        }

        if ($event->status === WebhookStatus::Failed) {
            return ApiResponse::error(ApiErrorCode::InternalError, 'Notifikasi belum dapat diproses.', 500);
        }

        return ApiResponse::success(['received' => true]);
    }
}
