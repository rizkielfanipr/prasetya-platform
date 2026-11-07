<?php

declare(strict_types=1);

namespace App\Domain\Audit\Queries;

use App\Domain\Audit\Models\AuditLog;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where each audit entry is shown (ADR-0010). Read-only: audit stays append-only.
 *
 * - Transactions: entity order or payment, on the transaction detail.
 * - A user: every entry whose entity is that user, on the user detail.
 * - Content: entity content_item, on the Content Studio item detail (ADR-0011).
 * - System: everything that is not a transaction or the management of a user (sign-in and sign-out, profile and
 *   password changes, packages, unknown entities), under Pengaturan → Aktivitas Sistem. Sign-in and sign-out are
 *   about a user too, so they also appear on that user's detail.
 */
final class AuditTrail
{
    public const array TRANSACTION_ENTITIES = ['order', 'payment'];

    /** Entities with their own detail page outside transactions; they never appear in system activity. */
    public const array CONTENT_ENTITIES = ['content_item'];

    /** Actions that manage a user; they belong to that user's detail, not to system activity. */
    public const array USER_MANAGEMENT_ACTIONS = ['user.created', 'user.updated', 'user.activated', 'user.deactivated', 'user.password_reset'];

    /** @return Builder<AuditLog> */
    public function forUser(string $publicId): Builder
    {
        return AuditLog::query()->forEntities(['user' => [$publicId]]);
    }

    /** @return Builder<AuditLog> */
    public function forContentItem(string $publicId): Builder
    {
        return AuditLog::query()->forEntities(['content_item' => [$publicId]]);
    }

    /**
     * @param  array{search?: string|null, date_from?: string|null, date_to?: string|null, scope?: string|null}  $filters
     * @return Builder<AuditLog>
     */
    public function search(array $filters): Builder
    {
        $range = DateRange::fromBusinessDates($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        return AuditLog::query()
            ->when(($filters['scope'] ?? 'system') !== 'all', static fn (Builder $q) => $q
                ->whereNotIn('entity_type', [...self::TRANSACTION_ENTITIES, ...self::CONTENT_ENTITIES])
                ->whereNotIn('action', self::USER_MANAGEMENT_ACTIONS))
            ->when($filters['search'] ?? null, static function (Builder $q, string $search): void {
                $term = addcslashes($search, '%_\\');
                $q->where(fn (Builder $inner) => $inner->where('action', 'like', "{$term}%")->orWhere('entity_id', 'like', "{$term}%")->orWhere('actor_label', 'like', "{$term}%"));
            })
            ->when($range !== null, static fn (Builder $q) => $q->whereBetween('created_at', [$range->start, $range->end]));
    }
}
