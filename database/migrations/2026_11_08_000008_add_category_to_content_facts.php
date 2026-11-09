<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_facts', function (Blueprint $table): void {
            $table->string('category', 24)->default('GENERAL')->after('content_campaign_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('content_facts', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
