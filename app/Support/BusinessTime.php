<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/** Times typed by people are business time (WIB); the database stores UTC. */
final class BusinessTime
{
    /** The format of an HTML datetime-local input. */
    public const string LOCAL_FORMAT = 'Y-m-d\TH:i';

    public static function zone(): string
    {
        return (string) config('platform.business_timezone');
    }

    public static function parseLocal(string $value): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat(self::LOCAL_FORMAT, $value, self::zone());
        if (! $parsed instanceof CarbonImmutable) {
            throw new \InvalidArgumentException('Not a local date-time.');
        }

        return $parsed->utc();
    }
}
