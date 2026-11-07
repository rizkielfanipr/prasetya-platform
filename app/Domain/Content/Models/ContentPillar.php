<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A recurring theme of a brand, with the share of a week's posts it should get.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_brand_id
 * @property string $name
 * @property string|null $description
 * @property int $target_share
 * @property bool $is_active
 */
class ContentPillar extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** Mirrors the column defaults, so a new model reads the same as a stored one. */
    protected $attributes = ['is_active' => true, 'target_share' => 0];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'target_share' => 'integer'];
    }
}
