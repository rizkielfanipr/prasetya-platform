<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The image of one revision, stored privately by checksum. Files are shared between revisions that carry the same
 * image; rows are not.
 *
 * @property int $id
 * @property string $public_id
 * @property int $content_revision_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property string $checksum_sha256
 * @property int $uploaded_by_user_id
 * @property Carbon $created_at
 * @property-read ContentRevision $revision
 */
class ContentAsset extends Model
{
    use HasUlids;

    public const null UPDATED_AT = null;

    protected $guarded = [];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'content_revision_id');
    }

    /** File name offered on download: the item's title is not trusted here, so a fixed, safe pattern. */
    public function downloadName(): string
    {
        return 'konten-'.$this->public_id.'.'.match ($this->mime_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
