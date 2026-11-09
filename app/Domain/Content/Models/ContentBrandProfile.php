<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Auth\Models\User;
use App\Support\Eloquent\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a brand's rules. Append-only: a change is a new version, so the rules a revision was checked against
 * can always be shown again.
 *
 * @property int $id
 * @property int $content_brand_id
 * @property int $version
 * @property string|null $audience
 * @property string|null $voice
 * @property string|null $visual_guidance
 * @property list<string> $preferred_terms
 * @property list<string> $forbidden_terms
 * @property list<string> $forbidden_claims
 * @property list<string> $allowed_ctas
 * @property list<string> $required_hashtags
 * @property list<string> $forbidden_hashtags
 * @property list<string> $approved_examples
 * @property list<string> $rejected_examples
 * @property int|null $caption_min_length
 * @property int|null $caption_max_length
 * @property int|null $hashtag_min
 * @property int|null $hashtag_max
 * @property int|null $topic_repeat_days
 * @property string $change_note
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property-read User|null $creator
 */
class ContentBrandProfile extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /** List fields, each a bounded list of short phrases. */
    public const array LISTS = ['preferred_terms', 'forbidden_terms', 'forbidden_claims', 'allowed_ctas', 'required_hashtags', 'forbidden_hashtags', 'approved_examples', 'rejected_examples'];

    public const array TEXTS = ['audience', 'voice', 'visual_guidance'];

    public const array NUMBERS = ['caption_min_length', 'caption_max_length', 'hashtag_min', 'hashtag_max', 'topic_repeat_days'];

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array<string, mixed> The rule values only, for comparing versions. */
    public function rules(): array
    {
        return $this->only([...self::TEXTS, ...self::LISTS, ...self::NUMBERS]);
    }

    protected function casts(): array
    {
        return array_fill_keys(self::LISTS, 'array') + array_fill_keys(self::NUMBERS, 'integer') + ['version' => 'integer'];
    }
}
