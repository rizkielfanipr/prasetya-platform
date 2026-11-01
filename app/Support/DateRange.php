<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/** A range of business-timezone calendar days, exposed as UTC instants for querying. */
final readonly class DateRange
{
    public function __construct(public Carbon $start, public Carbon $end) {}

    public static function fromBusinessDates(?string $from, ?string $to): ?self
    {
        if ($from === null && $to === null) {
            return null;
        }

        $timezone = (string) config('platform.business_timezone');

        return new self(
            ($from !== null ? Carbon::parse($from, $timezone) : Carbon::create(2000, 1, 1, 0, 0, 0, $timezone))->startOfDay()->utc(),
            ($to !== null ? Carbon::parse($to, $timezone) : Carbon::now($timezone))->endOfDay()->utc(),
        );
    }

    public static function lastDays(int $days): self
    {
        $timezone = (string) config('platform.business_timezone');
        $today = Carbon::now($timezone);

        return new self($today->copy()->subDays($days - 1)->startOfDay()->utc(), $today->copy()->endOfDay()->utc());
    }

    public function previous(): self
    {
        $seconds = (int) $this->start->diffInSeconds($this->end) + 1;

        return new self($this->start->copy()->subSeconds($seconds), $this->start->copy()->subSecond());
    }
}
