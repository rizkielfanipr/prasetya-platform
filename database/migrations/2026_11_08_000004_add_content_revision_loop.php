<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Studio, phase 6 (ADR-0011): which parts a revision request names, the writer's extra instruction for an AI
 * run, and people's verdict on an AI draft for evaluation. Nullable additions only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_approvals', function (Blueprint $table): void {
            $table->json('areas')->nullable()->after('decision');
        });

        Schema::table('content_generation_runs', function (Blueprint $table): void {
            $table->string('instruction', 500)->nullable()->after('fields');
            $table->boolean('used_review_note')->default(false)->after('instruction');
            $table->string('feedback', 16)->nullable()->after('provider_request_id');
            $table->string('feedback_reason', 500)->nullable()->after('feedback');
            $table->foreignId('feedback_by_user_id')->nullable()->after('feedback_reason')->constrained('users');
            $table->timestamp('feedback_at')->nullable()->after('feedback_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('content_generation_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('feedback_by_user_id');
            $table->dropColumn(['instruction', 'used_review_note', 'feedback', 'feedback_reason', 'feedback_at']);
        });
        Schema::table('content_approvals', function (Blueprint $table): void {
            $table->dropColumn('areas');
        });
    }
};
