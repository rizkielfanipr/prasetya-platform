<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Content Studio AI choices made on screen (phase 3): which allowlisted copy model is in use. Never a key. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('value', 255);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_ai_settings');
    }
};
