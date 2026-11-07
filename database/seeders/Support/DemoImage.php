<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Builds a valid single-colour PNG without GD, so demo data and tests run where GD is not installed (CI, cPanel).
 */
final class DemoImage
{
    /** @param  array{int, int, int}  $rgb */
    public static function png(int $width, int $height, array $rgb = [242, 140, 40]): string
    {
        $row = "\0".str_repeat(pack('C3', ...$rgb), $width);
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        // Row by row, so memory stays near one row instead of the whole raw image.
        $deflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        if ($deflate === false) {
            throw new \RuntimeException('zlib is not available.');
        }
        $data = '';
        for ($y = 0; $y < $height; $y++) {
            $data .= deflate_add($deflate, $row, ZLIB_NO_FLUSH);
        }
        $data .= deflate_add($deflate, '', ZLIB_FINISH);

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', $data)
            .$chunk('IEND', '');
    }

    /**
     * Writes a PNG to a temporary file and returns its path.
     *
     * @param  array{int, int, int}  $rgb
     */
    public static function file(int $width = 1080, int $height = 1350, array $rgb = [242, 140, 40]): string
    {
        $path = tempnam(sys_get_temp_dir(), 'content-image-');
        if ($path === false) {
            throw new \RuntimeException('No temporary file for the demo image.');
        }
        file_put_contents($path, self::png($width, $height, $rgb));

        return $path;
    }
}
