<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ADR-0009: one access level. Every active user can use every back-office screen, so roles go away. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    /** The original roles cannot be restored: everyone comes back as SUPER_ADMIN, the old full-access role. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('SUPER_ADMIN')->index()->after('email');
        });
    }
};
