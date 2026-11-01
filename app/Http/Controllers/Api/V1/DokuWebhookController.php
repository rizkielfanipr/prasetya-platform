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

final class DokuWebhookController
{
    private const int MAX_BODY_BYTES = 65536;

    /**
     * DOKU needs a 2xx to stop retrying. Authentic deliveries are acknowledged even when rejected: the evidence is
     * stored and a retry cannot change the answer. Only a processing failure asks DOKU to retry.
     */
    public function __invoke(Request $request, ProcessGatewayNotification $process): JsonResponse
    {
        $rawBody = $request->getContent();

        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            return ApiResponse::error(ApiErrorCode::WebhookRejected, 'Notifikasi ditolak.', 413);
        }

        $event = $process(new InboundNotification(
            clientId: $request->header('Client-Id'),
            requestId: $request->header('Request-Id'),
            requestTimestamp: $request->header('Request-Timestamp'),
            signature: $request->header('Signature'),
            rawBody: $rawBody,
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
