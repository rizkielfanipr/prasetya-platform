<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content Studio, phase 8 (ADR-0011): Instagram metrics copied by hand from a published post's insights. Append-only:
 * a correction or a later reading is a new snapshot; summaries use each post's latest one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_insight_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('content_item_id')->constrained('content_items');
            $table->timestamp('measured_at');
            $table->unsignedInteger('reach');
            $table->unsignedInteger('views')->nullable();
            $table->unsignedInteger('likes')->nullable();
            $table->unsignedInteger('comments')->nullable();
            $table->unsignedInteger('saves')->nullable();
            $table->unsignedInteger('shares')->nullable();
            $table->unsignedInteger('profile_visits')->nullable();
            $table->unsignedInteger('follows')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->index(['content_item_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_insight_snapshots');
    }
};
