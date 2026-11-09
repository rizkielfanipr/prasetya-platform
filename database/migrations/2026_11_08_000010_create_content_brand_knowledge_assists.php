<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Minimal AI run metadata for the Brand Knowledge interview; answers and generated documents are not retained here. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_brand_knowledge_assists', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id');
            $table->unsignedBigInteger('content_brand_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->string('status', 16)->index('cbka_status_idx');
            $table->string('provider', 32);
            $table->string('model', 120)->nullable();
            $table->string('prompt_version', 32);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('provider_request_id', 120)->nullable();
            $table->timestamp('started_at')->nullable()->index('cbka_started_idx');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique('public_id', 'cbka_public_unique');
            $table->foreign('content_brand_id', 'cbka_brand_fk')->references('id')->on('content_brands');
            $table->foreign('requested_by_user_id', 'cbka_user_fk')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_brand_knowledge_assists');
    }
};
