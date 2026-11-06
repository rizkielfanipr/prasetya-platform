<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0009: sign in with a username. Existing users get one derived from their email, the email becomes optional
 * and unused, and email password reset goes away. The backfill is written out here, not taken from App\Domain\Auth,
 * so this migration behaves the same if those rules change later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 32)->nullable()->after('name');
        });

        $taken = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $username = $this->usernameFrom((string) $user->email, $taken);
            $taken[$username] = true;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 32)->nullable(false)->change();
            $table->unique('username');
            $table->string('email')->nullable()->change();
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Emails stay nullable: users created after this migration may have none.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /** @param array<string, true> $taken */
    private function usernameFrom(string $email, array $taken): string
    {
        $local = strtolower(explode('@', $email)[0]);
        $base = trim((string) preg_replace('/[^a-z0-9._-]+/', '-', $local), '._-');
        $base = substr($base, 0, 32);
        $base = rtrim($base, '._-');

        if (strlen($base) < 3) {
            $base = str_pad($base === '' ? 'user' : $base, 3, '0');
        }

        $candidate = $base;

        for ($n = 2; isset($taken[$candidate]); $n++) {
            $suffix = "-{$n}";
            $candidate = rtrim(substr($base, 0, 32 - strlen($suffix)), '._-').$suffix;
        }

        return $candidate;
    }
};
