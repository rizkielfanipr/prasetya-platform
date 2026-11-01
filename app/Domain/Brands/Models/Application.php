<?php

declare(strict_types=1);

namespace App\Domain\Brands\Models;

use App\Domain\Brands\Enums\ApplicationCode;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property ApplicationCode $code
 * @property string $name
 * @property string $invoice_prefix
 * @property bool $is_active
 */
class Application extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['code' => ApplicationCode::class, 'is_active' => 'boolean'];
    }
}
