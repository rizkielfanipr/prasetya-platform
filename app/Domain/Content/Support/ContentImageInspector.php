<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Exceptions\InvalidContentImage;

/**
 * Accepts a post image by its bytes, never by its name or the browser's claimed type (ADR-0011): JPEG, PNG, or WebP,
 * exactly 4:5, at least 1080×1350, within size and pixel limits. Dimensions are read from the header, so nothing is
 * decoded.
 */
final class ContentImageInspector
{
    public const int MAX_BYTES = 8 * 1024 * 1024;

    public const int MIN_WIDTH = 1080;

    public const int MIN_HEIGHT = 1350;

    /** 5000×6250: well above what Instagram keeps, and a cap on decompression bombs if anything ever decodes it. */
    public const int MAX_PIXELS = 31_250_000;

    /** @var array<string, array{string, int}> mime => [extension, IMAGETYPE_*] */
    private const array TYPES = [
        'image/jpeg' => ['jpg', IMAGETYPE_JPEG],
        'image/png' => ['png', IMAGETYPE_PNG],
        'image/webp' => ['webp', IMAGETYPE_WEBP],
    ];

    public function inspect(string $path): InspectedImage
    {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            throw new InvalidContentImage('File gambar kosong atau tidak terbaca.');
        }
        if ($size > self::MAX_BYTES) {
            throw new InvalidContentImage('Ukuran gambar maksimal 8 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $type = is_string($mime) ? (self::TYPES[$mime] ?? null) : null;
        $header = @getimagesize($path);
        if ($type === null || $header === false || $header[2] !== $type[1]) {
            throw new InvalidContentImage('Gambar harus berformat JPEG, PNG, atau WebP.');
        }

        [$width, $height] = $header;
        if ($width * $height > self::MAX_PIXELS) {
            throw new InvalidContentImage('Resolusi gambar terlalu besar. Maksimal 5000×6250 piksel.');
        }
        if ($width * 5 !== $height * 4) {
            throw new InvalidContentImage("Rasio gambar harus tepat 4:5 (mis. 1080×1350). Gambar ini {$width}×{$height}.");
        }
        if ($width < self::MIN_WIDTH) {
            throw new InvalidContentImage("Gambar minimal 1080×1350 piksel. Gambar ini {$width}×{$height}.");
        }

        /** @var string $mime */
        return new InspectedImage($mime, $type[0], $width, $height, $size, (string) hash_file('sha256', $path));
    }
}
