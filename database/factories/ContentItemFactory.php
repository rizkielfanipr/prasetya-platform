<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft item with an empty first revision. Tests move it through the real actions rather than setting statuses.
 *
 * @extends Factory<ContentItem>
 */
class ContentItemFactory extends Factory
{
    protected $model = ContentItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'content_brand_id' => fn () => ContentBrand::query()->where('code', 'WEBWORKS')->value('id') ?? ContentBrand::factory()->create()->id,
            'title' => fake()->sentence(4),
            'planned_at' => now()->addDays(2)->setTime(3, 0),
            'status' => ContentStatus::Draft,
            'created_by_user_id' => fn () => User::factory()->create()->id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ContentItem $item): void {
            if ($item->active_revision_id !== null) {
                return;
            }
            $revision = ContentRevision::query()->create([
                'content_item_id' => $item->id,
                'number' => 1,
                'author_user_id' => $item->created_by_user_id,
            ]);
            $item->forceFill(['active_revision_id' => $revision->id])->save();
        });
    }
}
