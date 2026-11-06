<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('provider', 32);
            $table->string('status', 32);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('IDR');
            // Sent to Duitku as merchantOrderId; a retry gets a suffix so each attempt maps to one notification stream.
            $table->string('gateway_invoice_number', 30)->unique();
            $table->string('gateway_request_id', 64)->unique();
            $table->string('gateway_reference', 128)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'attempt_number']);
            $table->index(['status', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'paid_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('provider', 32);
            $table->string('provider_request_id', 128)->nullable();
            $table->string('status', 32);
            $table->string('rejection_reason', 64)->nullable();
            $table->boolean('signature_valid');
            $table->char('dedupe_key', 64)->nullable()->index();
            // Set only on the first delivery; NULLs do not collide, so this enforces one primary per event.
            $table->char('primary_dedupe_key', 64)->nullable()->unique();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('webhook_events')->nullOnDelete();
            $table->string('invoice_number', 64)->nullable()->index();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('transaction_status', 32)->nullable();
            $table->string('channel', 64)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->char('payload_digest', 64);
            // Allow-listed fields only. Signatures and raw bodies are never stored.
            $table->json('sanitized_payload')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index(['rejection_reason', 'received_at']);
            $table->index('received_at');
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('webhook_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 48);
            $table->string('source', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'occurred_at']);
            $table->unique(['webhook_event_id', 'type']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 64);
            $table->string('idempotency_key', 128);
            $table->char('request_fingerprint', 64);
            $table->string('status', 16);
            $table->string('resource_id', 64)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'idempotency_key']);
            $table->index('created_at');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label');
            $table->string('action', 64);
            $table->string('entity_type', 64);
            $table->string('entity_id', 64)->nullable();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->string('reason')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
    }
};
