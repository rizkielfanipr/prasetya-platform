<?php

declare(strict_types=1);

namespace App\Support\Api;

enum ApiErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case RateLimited = 'RATE_LIMITED';
    case OriginNotAllowed = 'ORIGIN_NOT_ALLOWED';
    case InternalError = 'INTERNAL_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case ApplicationNotFound = 'APPLICATION_NOT_FOUND';
    case PackageNotFound = 'PACKAGE_NOT_FOUND';
    case PackageInactive = 'PACKAGE_INACTIVE';
    case PackageApplicationMismatch = 'PACKAGE_APPLICATION_MISMATCH';
    case IdempotencyKeyRequired = 'IDEMPOTENCY_KEY_REQUIRED';
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case IdempotencyInProgress = 'IDEMPOTENCY_IN_PROGRESS';
    case OrderNotPayable = 'ORDER_NOT_PAYABLE';
    case OrderAlreadyPaid = 'ORDER_ALREADY_PAID';
    case CheckoutInProgress = 'CHECKOUT_IN_PROGRESS';
    case GatewayUnavailable = 'GATEWAY_UNAVAILABLE';
    case WebhookRejected = 'WEBHOOK_REJECTED';
}
