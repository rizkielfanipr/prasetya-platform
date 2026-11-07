<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property int|null $updated_by_user_id
 */
class ContentAiSetting extends Model
{
    public const string COPY_MODEL = 'copy_model';

    protected $guarded = [];

    public static function value(string $key): ?string
    {
        $value = self::query()->where('key', $key)->value('value');

        return is_string($value) ? $value : null;
    }
}
