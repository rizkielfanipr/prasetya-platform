<?php

declare(strict_types=1);

namespace App\Http\Workspace;

use App\Domain\Payments\Queries\TransactionQuery;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\BackOffice\TransactionFilterRequest;
use App\Support\Paginated;
use Illuminate\Http\Request;

/** Transaksi → Semua: the transaction list with every filter it already had. */
final class AllTransactionsView
{
    public const array FILTERS = ['search', 'brand', 'payment_status', 'order_status', 'payment_method', 'package', 'theme_code', 'event_type', 'event_date', 'date_from', 'date_to', 'sort', 'direction'];

    public function __construct(private readonly TransactionQuery $query) {}

    /** @return array<string, mixed> */
    public function props(Request $request): array
    {
        $filters = $request->validate((new TransactionFilterRequest)->rules());

        return [
            'filters' => array_merge(array_fill_keys(self::FILTERS, null), array_intersect_key($filters, array_flip(self::FILTERS))),
            ...$this->query->filterOptions(),
            'transactions' => Paginated::present($this->query->paginate($filters), TransactionPresenter::row(...)),
        ];
    }
}
