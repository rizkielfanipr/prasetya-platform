<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Private, content-addressed storage for post images: one file per checksum, shared by every revision that carries it.
 * Files are never served directly; ContentAssetController streams them to signed-in users.
 */
final class ContentAssetStore
{
    public function disk(): string
    {
        return (string) config('content.disk');
    }

    /** Stores the file if this checksum is new and returns its path on the disk. */
    public function put(string $sourcePath, InspectedImage $image): string
    {
        $path = "content-assets/{$image->checksum}.{$image->extension}";
        $disk = Storage::disk($this->disk());

        if (! $disk->exists($path)) {
            $stream = fopen($sourcePath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Uploaded image could not be read.');
            }
            try {
                $disk->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }
        }

        return $path;
    }
}
