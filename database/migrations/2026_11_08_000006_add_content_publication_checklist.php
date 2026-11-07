<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Content Studio, phase 7 (ADR-0011): the posting checklist the person confirmed, kept as evidence. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_publications', function (Blueprint $table): void {
            $table->json('checklist')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('content_publications', function (Blueprint $table): void {
            $table->dropColumn('checklist');
        });
    }
};
