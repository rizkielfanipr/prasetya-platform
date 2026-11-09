<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_brand_knowledge_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_brand_id')->constrained('content_brands');
            $table->unsignedInteger('version');
            $table->longText('source_text');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('created_at')->nullable();
            $table->unique(['content_brand_id', 'version']);
        });

        Schema::table('content_brands', function (Blueprint $table): void {
            $table->foreignId('current_knowledge_version_id')->nullable()->after('current_profile_id')->constrained('content_brand_knowledge_versions');
        });

        Schema::create('content_brand_knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('knowledge_version_id');
            $table->unsignedSmallInteger('position');
            $table->text('content');
            $table->foreign('knowledge_version_id', 'kb_chunks_version_fk')->references('id')->on('content_brand_knowledge_versions')->cascadeOnDelete();
            $table->unique(['knowledge_version_id', 'position'], 'kb_chunks_version_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_brand_knowledge_chunks');
        Schema::table('content_brands', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_knowledge_version_id');
        });
        Schema::dropIfExists('content_brand_knowledge_versions');
    }
};
