<?php

declare(strict_types=1);

use App\Domain\Auth\Username;
use Illuminate\Support\Facades\Schema;

/** Runs the migration's own backfill rule over a list of emails, as up() does row by row. */
function backfill(array $emails): array
{
    $migration = require database_path('migrations/2026_11_07_000001_sign_in_with_username.php');
    $taken = [];

    return array_map(function (string $email) use ($migration, &$taken): string {
        $username = (fn (string $e, array $t): string => $this->usernameFrom($e, $t))->call($migration, $email, $taken);
        $taken[$username] = true;

        return $username;
    }, $emails);
}

it('derives valid, unique usernames from existing emails', function (): void {
    $usernames = backfill([
        'Sari.Lestari@example.test',
        'sari.lestari@another.test',
        'sari.lestari@third.test',
        'budi+tag@example.test',
        'x@example.test',
        '..__@example.test',
        str_repeat('a', 40).'@example.test',
        str_repeat('a', 40).'@other.test',
        'ÄNNE@example.test',
    ]);

    expect($usernames)->toBe([
        'sari.lestari',
        'sari.lestari-2',
        'sari.lestari-3',
        'budi-tag',
        'x00',
        'user',
        str_repeat('a', 32),
        str_repeat('a', 30).'-2',
        'nne',
    ]);

    foreach ($usernames as $username) {
        expect(Username::isValid($username))->toBeTrue("{$username} is not valid");
    }
    expect(array_unique($usernames))->toHaveCount(count($usernames));
});

it('leaves username required and unique, and email optional', function (): void {
    expect(Schema::hasColumn('users', 'username'))->toBeTrue()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(collect(Schema::getColumns('users'))->firstWhere('name', 'email')['nullable'])->toBeTrue()
        ->and(collect(Schema::getColumns('users'))->firstWhere('name', 'username')['nullable'])->toBeFalse()
        ->and(collect(Schema::getIndexes('users'))->contains(fn (array $index): bool => $index['columns'] === ['username'] && $index['unique']))->toBeTrue();
});
