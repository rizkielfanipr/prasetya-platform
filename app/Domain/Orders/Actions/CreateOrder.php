<?php

declare(strict_types=1);

namespace App\Domain\Orders\Actions;

use App\Domain\Brands\Enums\ApplicationCode;
use App\Domain\Brands\Models\Application;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Data\CreateOrderData;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\TransitionSource;
use App\Domain\Orders\Models\InvitationOrderDetail;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Orders\Models\WebworksOrderDetail;
use App\Domain\Orders\Services\InvoiceNumberGenerator;
use App\Domain\Packages\Services\PackageCatalogue;
use App\Support\Api\ApiErrorCode;
use App\Support\Api\ApiException;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final class CreateOrder
{
    public function __construct(
        private readonly PackageCatalogue $catalogue,
        private readonly InvoiceNumberGenerator $invoices,
        private readonly RequestContext $context,
    ) {}

    public function __invoke(CreateOrderData $data): Order
    {
        $application = Application::query()->where('code', $data->application)->where('is_active', true)->first()
            ?? throw new ApiException(ApiErrorCode::ApplicationNotFound, 'Brand tidak tersedia.', 422, ['application_code' => ['Brand tidak tersedia.']]);

        $package = $this->catalogue->purchasable($application, $data->packageCode);

        return DB::transaction(function () use ($application, $package, $data): Order {
            // An existing customer row is never overwritten by public input; the order keeps its own snapshot.
            $customer = Customer::query()->firstOrCreate(
                ['application_id' => $application->id, 'email' => $data->customerEmail],
                ['name' => $data->customerName, 'phone' => $data->customerPhone],
            );

            $price = $package->price();

            $order = Order::query()->create([
                'application_id' => $application->id,
                'customer_id' => $customer->id,
                'invoice_number' => $this->invoices->next($application),
                'status' => OrderStatus::PendingPayment,
                'total_amount' => $price->amount,
                'currency' => $price->currency,
                'customer_name' => $data->customerName,
                'customer_email' => $data->customerEmail,
                'customer_phone' => $data->customerPhone,
                'expires_at' => now()->addHours((int) config('platform.order_expiry_hours')),
            ]);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'package_id' => $package->id,
                'package_code' => $package->code,
                'package_name' => $package->name,
                'unit_amount' => $price->amount,
                'quantity' => 1,
                'line_amount' => $price->amount,
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment,
                'source' => TransitionSource::Api,
                'request_id' => $this->context->requestId(),
            ]);

            match ($data->application) {
                ApplicationCode::Webworks => WebworksOrderDetail::query()->create(['order_id' => $order->id, ...$data->details]),
                ApplicationCode::Satamu => InvitationOrderDetail::query()->create(['order_id' => $order->id, ...$data->details]),
            };

            return $order;
        });
    }
}
