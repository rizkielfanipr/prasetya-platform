<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('invoice_prefix', 8)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'email']);
            $table->index('name');
        });

        Schema::create('packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // Whole rupiah: IDR has no minor unit in DOKU Checkout (order.amount has no decimals).
            $table->unsignedBigInteger('price_amount');
            $table->char('currency', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['application_id', 'is_active', 'sort_order']);
        });

        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->char('period', 6);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['application_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('applications');
    }
};
