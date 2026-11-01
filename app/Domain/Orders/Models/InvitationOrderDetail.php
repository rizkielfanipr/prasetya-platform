<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string|null $theme_code
 * @property string|null $event_type
 * @property Carbon|null $event_date
 * @property string|null $notes
 */
class InvitationOrderDetail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }
}
