<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A deterministic retrieval unit derived from an immutable Brand Knowledge version.
 *
 * @property int $id
 * @property int $knowledge_version_id
 * @property int $position
 * @property string $content
 */
final class ContentBrandKnowledgeChunk extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
