<?php

declare(strict_types=1);

namespace App\Domain\Packages\Models;

use App\Domain\Brands\Models\Application;
use App\Support\Money;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $price_amount
 * @property string $currency
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Application $application
 */
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function price(): Money
    {
        return new Money($this->price_amount, $this->currency);
    }

    protected function casts(): array
    {
        return ['price_amount' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
