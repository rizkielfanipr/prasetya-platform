<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Brands\Models\Application;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class InvoiceNumberGenerator
{
    /** Format: PREFIX-YYYYMM-NNNNNN, the month taken in the business timezone. Must run inside a transaction. */
    public function next(Application $application, ?DateTimeInterface $at = null): string
    {
        $period = Carbon::instance($at ?? now())->setTimezone((string) config('platform.business_timezone'))->format('Ym');

        DB::table('invoice_sequences')->insertOrIgnore([
            'application_id' => $application->id,
            'period' => $period,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('invoice_sequences')
            ->where(['application_id' => $application->id, 'period' => $period])
            ->lockForUpdate()
            ->first();

        $number = (int) $sequence->last_number + 1;

        DB::table('invoice_sequences')->where('id', $sequence->id)->update(['last_number' => $number, 'updated_at' => now()]);

        return self::format($application->invoice_prefix, $period, $number);
    }

    public static function format(string $prefix, string $period, int $number): string
    {
        return sprintf('%s-%s-%06d', $prefix, $period, $number);
    }
}
