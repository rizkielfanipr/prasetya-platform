<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Studio, phase 1 (ADR-0011): brands, items, immutable-once-submitted revisions, their images, append-only
 * review decisions, and the publication record. New tables only; nothing existing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_brands', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->string('title', 160);
            $table->dateTime('planned_at')->index();
            $table->string('status', 32)->index();
            // Set after the first revision exists; both point into content_revisions.
            $table->unsignedBigInteger('active_revision_id')->nullable();
            $table->unsignedBigInteger('approved_revision_id')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_item_id')->constrained('content_items');
            $table->unsignedInteger('number');
            $table->text('objective')->nullable();
            $table->text('audience')->nullable();
            $table->text('brief')->nullable();
            $table->text('caption')->nullable();
            $table->text('hashtags')->nullable();
            $table->string('alt_text', 1000)->nullable();
            $table->text('visual_direction')->nullable();
            $table->foreignId('author_user_id')->constrained('users');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['content_item_id', 'number']);
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->foreign('active_revision_id')->references('id')->on('content_revisions');
            $table->foreign('approved_revision_id')->references('id')->on('content_revisions');
        });

        Schema::create('content_assets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_revision_id')->unique()->constrained('content_revisions');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 64);
            $table->unsignedInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum_sha256', 64)->index();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('content_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_revision_id')->constrained('content_revisions');
            $table->string('decision', 32);
            $table->text('note')->nullable();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('content_publications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_item_id')->unique()->constrained('content_items');
            $table->foreignId('content_revision_id')->constrained('content_revisions');
            $table->dateTime('published_at');
            $table->string('url', 500)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_publications');
        Schema::dropIfExists('content_approvals');
        Schema::dropIfExists('content_assets');
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropForeign(['active_revision_id']);
            $table->dropForeign(['approved_revision_id']);
        });
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_brands');
    }
};
