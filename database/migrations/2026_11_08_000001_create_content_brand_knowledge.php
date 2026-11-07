<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Studio, phase 2 (ADR-0011): versioned brand profiles, approved facts, pillars with a target share, and
 * campaigns. New tables and nullable columns only; nothing existing changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Append-only: each change to a brand's rules is a new version, so a revision can name the rules it met.
        Schema::create('content_brand_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->unsignedInteger('version');
            $table->text('audience')->nullable();
            $table->text('voice')->nullable();
            $table->text('visual_guidance')->nullable();
            // Bounded lists of short phrases (validated: at most 50 items of 200 characters).
            $table->json('preferred_terms');
            $table->json('forbidden_terms');
            $table->json('forbidden_claims');
            $table->json('allowed_ctas');
            $table->json('required_hashtags');
            $table->json('forbidden_hashtags');
            $table->json('approved_examples');
            $table->json('rejected_examples');
            $table->unsignedSmallInteger('caption_min_length')->nullable();
            $table->unsignedSmallInteger('caption_max_length')->nullable();
            $table->unsignedTinyInteger('hashtag_min')->nullable();
            $table->unsignedTinyInteger('hashtag_max')->nullable();
            $table->unsignedSmallInteger('topic_repeat_days')->nullable();
            $table->string('change_note', 500);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->unique(['content_brand_id', 'version']);
        });

        Schema::table('content_brands', function (Blueprint $table): void {
            $table->foreignId('current_profile_id')->nullable()->after('is_active')->constrained('content_brand_profiles');
        });

        Schema::create('content_pillars', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('target_share')->default(0); // Percent of a week's posts.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['content_brand_id', 'name']);
        });

        Schema::create('content_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->string('name', 120);
            $table->text('objective')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('priority', 16);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A fact is never edited: correcting one retires it and adds another, so earlier reviews stay explainable.
        Schema::create('content_facts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->foreignId('content_campaign_id')->nullable()->constrained('content_campaigns');
            $table->text('statement');
            $table->string('source', 255);
            $table->date('valid_until')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('retired_by_user_id')->nullable()->constrained('users');
            $table->string('retired_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->foreignId('content_pillar_id')->nullable()->after('content_brand_id')->constrained('content_pillars');
            $table->foreignId('content_campaign_id')->nullable()->after('content_pillar_id')->constrained('content_campaigns');
            $table->string('topic', 80)->nullable()->after('title')->index();
        });

        Schema::table('content_revisions', function (Blueprint $table): void {
            // The brand rules a revision was checked against when it was submitted.
            $table->foreignId('brand_profile_id')->nullable()->after('submitted_at')->constrained('content_brand_profiles');
        });
    }

    public function down(): void
    {
        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('brand_profile_id');
        });
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_pillar_id');
            $table->dropConstrainedForeignId('content_campaign_id');
            $table->dropIndex(['topic']);
            $table->dropColumn('topic');
        });
        Schema::dropIfExists('content_facts');
        Schema::dropIfExists('content_campaigns');
        Schema::dropIfExists('content_pillars');
        Schema::table('content_brands', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_profile_id');
        });
        Schema::dropIfExists('content_brand_profiles');
    }
};
