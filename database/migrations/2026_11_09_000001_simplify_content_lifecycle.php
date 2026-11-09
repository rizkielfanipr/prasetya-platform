<?php

declare(strict_types=1);

use App\Domain\Content\Migration\LegacyStatusBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0012, phase 1: the creator-owned lifecycle (DRAFT, READY, PUBLISHED, ARCHIVED).
 *
 * - Adds ready_revision_id: the revision a person marked Ready. Approved items keep their approved revision as Ready.
 * - Maps statuses with LegacyStatusBackfill (LegacyStatusMap). Idempotent: every update matches only legacy values.
 * - Keeps approved_revision_id, content_approvals, decision areas, and checklists untouched as legacy evidence.
 * - Rollback restores compatible statuses only; it never creates an approval row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('content_items', 'ready_revision_id')) {
            Schema::table('content_items', function (Blueprint $table): void {
                $table->foreignId('ready_revision_id')->nullable()->after('approved_revision_id')->constrained('content_revisions');
            });
        }

        app(LegacyStatusBackfill::class)->forward();
    }

    public function down(): void
    {
        app(LegacyStatusBackfill::class)->backward();

        if (Schema::hasColumn('content_items', 'ready_revision_id')) {
            Schema::table('content_items', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('ready_revision_id');
            });
        }
    }
};
