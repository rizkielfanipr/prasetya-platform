<?php

declare(strict_types=1);

namespace App\Domain\Content\Migration;

/**
 * ADR-0012: how editorial statuses map onto the creator-owned lifecycle. Values only, so the mapping can be
 * rehearsed against any database before the new statuses exist in code. Rollback never invents approvals.
 */
final class LegacyStatusMap
{
    /** @var array<string, string> legacy status => target status */
    public const array FORWARD = [
        'DRAFT' => 'DRAFT',
        'READY_FOR_REVIEW' => 'DRAFT',
        'REVISION_REQUESTED' => 'DRAFT',
        'APPROVED' => 'READY',
        'PUBLISHED' => 'PUBLISHED',
        'REJECTED' => 'ARCHIVED',
    ];

    /**
     * Rollback to statuses the compatibility release understands. READY returns to APPROVED only as a status: no
     * approval row is created, so code must treat an APPROVED item without an approval row as legacy-compatible.
     *
     * @var array<string, string>
     */
    public const array BACKWARD = [
        'DRAFT' => 'DRAFT',
        'READY' => 'APPROVED',
        'PUBLISHED' => 'PUBLISHED',
        'ARCHIVED' => 'REJECTED',
    ];

    public static function forward(string $status): string
    {
        return self::FORWARD[$status] ?? throw new \InvalidArgumentException("Unknown legacy content status [{$status}].");
    }

    public static function backward(string $status): string
    {
        return self::BACKWARD[$status] ?? throw new \InvalidArgumentException("Unknown target content status [{$status}].");
    }
}
