<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

/** Pagination shape shared by every back-office list (types/pagination.ts mirrors it). */
final class Paginated
{
    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $paginator
     * @param  Closure(T): array<string, mixed>  $present
     * @return array{data: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int, from: int|null, to: int|null}}
     */
    public static function present(LengthAwarePaginator $paginator, Closure $present): array
    {
        return [
            'data' => array_values(array_map($present, $paginator->items())),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
