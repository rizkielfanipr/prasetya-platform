<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 30)->unique();
            $table->string('status', 32);
            $table->unsignedBigInteger('total_amount');
            $table->char('currency', 3)->default('IDR');
            // Snapshot: the customer row is shared across orders, an order keeps what was submitted.
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 32)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('created_at');
            $table->index('customer_name');
            $table->index('customer_email');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            // Immutable snapshot: historical totals do not change when the catalogue does.
            $table->string('package_code', 64);
            $table->string('package_name');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('line_amount');
            $table->timestamps();

            $table->index('package_code');
        });

        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('source', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('admin_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('webworks_order_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template_code', 64)->nullable();
            $table->string('business_name')->nullable();
            $table->string('domain_preference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invitation_order_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('theme_code', 64)->nullable();
            $table->string('event_type', 64)->nullable();
            $table->date('event_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_order_details');
        Schema::dropIfExists('webworks_order_details');
        Schema::dropIfExists('admin_notes');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
