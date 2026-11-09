<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Studio, phase 4 (ADR-0011): traceable research. Runs record each web search; sources keep the canonical
 * URL, title, domain, access time, a short summary, trust, and relevance, never the full article; citations link
 * sources to the items that use them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_research_runs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->foreignId('content_item_id')->nullable()->constrained('content_items');
            $table->string('query', 300);
            $table->string('status', 16)->index();
            $table->string('provider', 32);
            $table->unsignedTinyInteger('max_results');
            $table->unsignedSmallInteger('results_count')->default(0);
            $table->unsignedSmallInteger('dropped_count')->default(0);
            $table->json('result_source_ids')->nullable(); // The sources this search returned, new or already known.
            $table->unsignedSmallInteger('search_calls')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('content_research_sources', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->foreignId('content_research_run_id')->nullable()->constrained('content_research_runs');
            $table->string('origin', 16); // SEARCH or MANUAL
            $table->string('url', 2048);
            $table->char('url_hash', 64);
            $table->string('domain', 255)->index();
            $table->string('title', 300);
            $table->text('summary')->nullable();
            $table->string('trust', 16);
            $table->decimal('relevance', 4, 3)->nullable();
            $table->date('published_on')->nullable();
            $table->timestamp('fetched_at');
            $table->foreignId('added_by_user_id')->constrained('users');
            $table->timestamps();
            $table->unique(['content_brand_id', 'url_hash']);
        });

        Schema::create('content_item_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_item_id')->constrained('content_items');
            $table->foreignId('content_research_source_id')->constrained('content_research_sources');
            $table->string('note', 500)->nullable();
            $table->foreignId('linked_by_user_id')->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->unique(['content_item_id', 'content_research_source_id'], 'content_item_sources_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_item_sources');
        Schema::dropIfExists('content_research_sources');
        Schema::dropIfExists('content_research_runs');
    }
};
