<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\CampaignPriority;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A time-boxed push with an objective. Its period is in business-time days, inclusive.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_brand_id
 * @property string $name
 * @property string|null $objective
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property CampaignPriority $priority
 * @property bool $is_active
 */
class ContentCampaign extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** Mirrors the column defaults, so a new model reads the same as a stored one. */
    protected $attributes = ['is_active' => true];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** Whether a business-time day (Y-m-d) falls inside the campaign. */
    public function covers(CarbonInterface $day): bool
    {
        $date = $day->toDateString();

        return $date >= $this->starts_on->toDateString() && $date <= $this->ends_on->toDateString();
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'priority' => CampaignPriority::class, 'is_active' => 'boolean'];
    }
}
