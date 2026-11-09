<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Studio, phase 3 (ADR-0011): one row per AI copy generation, with usage and cost. Prompts and raw provider
 * responses are not stored; the accepted output lives in the revision it created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_item_id')->constrained('content_items');
            $table->foreignId('base_revision_id')->constrained('content_revisions');
            $table->foreignId('result_revision_id')->nullable()->constrained('content_revisions');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->json('fields');
            $table->string('status', 16)->index();
            $table->string('provider', 32);
            $table->string('model', 120)->nullable();
            $table->string('prompt_version', 32);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_code', 32)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('provider_request_id', 120)->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['content_item_id', 'status']);
        });

        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->foreignId('generation_run_id')->nullable()->after('brand_profile_id')->constrained('content_generation_runs');
        });
    }

    public function down(): void
    {
        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('generation_run_id');
        });
        Schema::dropIfExists('content_generation_runs');
    }
};
