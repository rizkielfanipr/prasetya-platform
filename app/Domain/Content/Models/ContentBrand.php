<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Database\Factories\ContentBrandFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class ContentBrand extends Model
{
    /** @use HasFactory<ContentBrandFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
