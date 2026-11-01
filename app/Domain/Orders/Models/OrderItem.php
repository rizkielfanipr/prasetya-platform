<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property string $package_code
 * @property string $package_name
 * @property int $unit_amount
 * @property int $quantity
 * @property int $line_amount
 */
class OrderItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['unit_amount' => 'integer', 'quantity' => 'integer', 'line_amount' => 'integer'];
    }
}
