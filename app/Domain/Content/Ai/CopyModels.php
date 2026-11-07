<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

use App\Domain\Content\Models\ContentAiSetting;

/**
 * The copy models this installation allows, from configuration, and the one in use. The screen may choose among
 * them; it can never name a model the server did not list, so every model in use has a known price.
 */
final class CopyModels
{
    private ?string $override = null;

    /** @return list<CopyModel> */
    public function available(): array
    {
        $models = [];
        foreach (array_filter(array_map('trim', explode(',', (string) config('content.ai.copy.models')))) as $entry) {
            $parts = array_map('trim', explode(':', $entry));
            if (count($parts) === 3 && $parts[0] !== '' && is_numeric($parts[1]) && is_numeric($parts[2])) {
                $models[$parts[0]] = new CopyModel($parts[0], (float) $parts[1], (float) $parts[2]);
            }
        }
        $default = config('content.ai.copy.model');
        if (is_string($default) && $default !== '' && ! isset($models[$default])) {
            $models[$default] = new CopyModel($default, (float) config('content.ai.copy.price_input_per_million'), (float) config('content.ai.copy.price_output_per_million'));
        }

        return array_values($models);
    }

    /** The chosen model when it is still allowed, else the configured default, else the first allowed one. */
    public function selected(): ?CopyModel
    {
        $available = $this->available();
        $wanted = $this->override ?? ContentAiSetting::value(ContentAiSetting::COPY_MODEL) ?? config('content.ai.copy.model');

        foreach ($available as $model) {
            if ($model->name === $wanted) {
                return $model;
            }
        }
        foreach ($available as $model) {
            if ($model->name === config('content.ai.copy.model')) {
                return $model;
            }
        }

        return $available[0] ?? null;
    }

    public function find(string $name): ?CopyModel
    {
        foreach ($this->available() as $model) {
            if ($model->name === $name) {
                return $model;
            }
        }

        return null;
    }

    /** Use another allowed model for this process only (evaluation), without changing the saved choice. */
    public function useForThisProcess(string $name): bool
    {
        if ($this->find($name) === null) {
            return false;
        }
        $this->override = $name;

        return true;
    }
}
