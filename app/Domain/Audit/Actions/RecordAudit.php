<?php

declare(strict_types=1);

namespace App\Domain\Audit\Actions;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\User;
use App\Support\RequestContext;

final class RecordAudit
{
    public const string SYSTEM_ACTOR = 'Sistem';

    public function __construct(private readonly RequestContext $context) {}

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $new
     */
    public function __invoke(
        string $action,
        string $entityType,
        ?string $entityId,
        ?User $actor = null,
        ?array $previous = null,
        ?array $new = null,
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'actor_label' => $actor !== null ? $actor->name : self::SYSTEM_ACTOR,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'previous_state' => $previous,
            'new_state' => $new,
            'reason' => $reason,
            'request_id' => $this->context->requestId(),
        ]);
    }
}
