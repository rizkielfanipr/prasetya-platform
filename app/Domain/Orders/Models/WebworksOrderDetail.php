<?php

declare(strict_types=1);

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $template_code
 * @property string|null $business_name
 * @property string|null $domain_preference
 * @property string|null $notes
 */
class WebworksOrderDetail extends Model
{
    protected $guarded = [];
}
