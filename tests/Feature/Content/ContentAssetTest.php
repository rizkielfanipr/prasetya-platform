<?php

declare(strict_types=1);

use App\Domain\Content\Models\ContentAsset;
use App\Domain\Content\Models\ContentItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/* ADR-0011, decision 9: images are accepted by their bytes, stored privately by checksum, and served only to users. */

beforeEach(fn () => contentSetUp());

function upload(ContentItem $item, UploadedFile $image): TestResponse
{
    return test()->actingAs(admin())->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'image' => $image]);
}

/** A JPEG whose header says width×height; enough for finfo and getimagesize, which never decode pixels. */
function jpegHeader(int $width, int $height): UploadedFile
{
    $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
        ."\xFF\xC0\x00\x11\x08".pack('nn', $height, $width)."\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xFF\xD9";

    return UploadedFile::fake()->createWithContent('post.jpg', $bytes);
}

/** An extended WebP (VP8X) header for width×height. */
function webpHeader(int $width, int $height): UploadedFile
{
    $vp8x = 'VP8X'.pack('V', 10)."\x00\x00\x00\x00".substr(pack('V', $width - 1), 0, 3).substr(pack('V', $height - 1), 0, 3);

    return UploadedFile::fake()->createWithContent('post.webp', 'RIFF'.pack('V', 4 + strlen($vp8x)).'WEBP'.$vp8x);
}

it('accepts a 4:5 image of at least 1080×1350 in JPEG, PNG, or WebP', function (Closure $image, string $mime): void {
    $item = draftItem(complete: false);

    upload($item, $image())->assertSessionHasNoErrors()->assertSessionHas('success');

    $asset = ContentAsset::query()->sole();
    expect($asset->mime_type)->toBe($mime)
        ->and([$asset->width, $asset->height])->toBe([1080, 1350])
        ->and($asset->path)->toStartWith('content-assets/'.$asset->checksum_sha256.'.')
        ->and($asset->disk)->toBe('local');
    Storage::disk('local')->assertExists($asset->path);
})->with([
    'png' => [fn () => postImage(), 'image/png'],
    'jpeg' => [fn () => jpegHeader(1080, 1350), 'image/jpeg'],
    'webp' => [fn () => webpHeader(1080, 1350), 'image/webp'],
]);

it('accepts a larger 4:5 image', function (): void {
    $item = draftItem(complete: false);

    upload($item, postImage(1440, 1800))->assertSessionHasNoErrors();
    expect(ContentAsset::query()->sole()->width)->toBe(1440);
});

it('rejects an image that is not an acceptable post', function (Closure $image, string $message): void {
    $item = draftItem(complete: false);

    upload($item, $image())->assertSessionHasErrors(['image' => $message]);
    expect(ContentAsset::query()->count())->toBe(0);
})->with([
    'square' => [fn () => postImage(1080, 1080), 'Rasio gambar harus tepat 4:5 (mis. 1080×1350). Gambar ini 1080×1080.'],
    'portrait 9:16' => [fn () => postImage(1080, 1920), 'Rasio gambar harus tepat 4:5 (mis. 1080×1350). Gambar ini 1080×1920.'],
    'too small' => [fn () => postImage(800, 1000), 'Gambar minimal 1080×1350 piksel. Gambar ini 800×1000.'],
    'text named png' => [fn () => UploadedFile::fake()->createWithContent('post.png', 'not an image at all'), 'Gambar harus berformat JPEG, PNG, atau WebP.'],
    'gif' => [fn () => UploadedFile::fake()->createWithContent('post.gif', 'GIF89a'.pack('vv', 1080, 1350)."\x00\x00\x00;"), 'Gambar harus berformat JPEG, PNG, atau WebP.'],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('post.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350"><script>alert(1)</script></svg>'), 'Gambar harus berformat JPEG, PNG, atau WebP.'],
    'png bytes named jpg' => [fn () => UploadedFile::fake()->createWithContent('post.jpg', "\x89PNG\r\n\x1a\nnot really"), 'Gambar harus berformat JPEG, PNG, atau WebP.'],
    'too many pixels' => [fn () => jpegHeader(8000, 10000), 'Resolusi gambar terlalu besar. Maksimal 5000×6250 piksel.'],
    'over 8 MB' => [fn () => UploadedFile::fake()->create('post.png', 9000, 'image/png'), 'Ukuran gambar maksimal 8 MB.'],
]);

it('stores identical bytes once, however many revisions carry them', function (): void {
    $first = draftItem(complete: false);
    $second = draftItem(complete: false);

    upload($first, postImage(rgb: [1, 2, 3]));
    upload($second, postImage(rgb: [1, 2, 3]));

    expect(ContentAsset::query()->count())->toBe(2)
        ->and(ContentAsset::query()->distinct()->count('path'))->toBe(1)
        ->and(Storage::disk('local')->allFiles('content-assets'))->toHaveCount(1);
});

it('streams an image to signed-in users only, inline or as a download', function (): void {
    $item = draftItem();
    $asset = ContentAsset::query()->sole();

    $this->get("/content/assets/{$asset->public_id}")->assertRedirect('/login');
    $this->actingAs(admin())->get("/content/assets/{$asset->id}")->assertNotFound();

    $inline = $this->get("/content/assets/{$asset->public_id}")->assertOk();
    expect($inline->headers->get('Content-Type'))->toBe('image/png')
        ->and($inline->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($inline->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($inline->streamedContent())->toBe(Storage::disk('local')->get($asset->path));

    $download = $this->get("/content/assets/{$asset->public_id}?download=1")->assertOk();
    expect($download->headers->get('Content-Disposition'))->toMatch('/^attachment; filename=\d{4}-\d{2}-\d{2}-kenapa-website-lambat-merugikan-r1\.png$/');
});

it('never sends the storage path or disk to the browser', function (): void {
    $item = draftItem();
    $asset = ContentAsset::query()->sole();

    $content = json_encode(contentPage("/content/items/{$item->public_id}"), JSON_UNESCAPED_SLASHES);

    expect($content)->toContain("/content/assets/{$asset->public_id}")
        ->not->toContain($asset->path)
        ->not->toContain('content-assets/');
});

it('keeps the original image on the old revision when a new one is uploaded', function (): void {
    $writer = admin();
    $item = readyItem($writer);
    $before = $item->refresh()->activeRevision?->asset?->checksum_sha256;

    $this->actingAs($writer)->put("/content/items/{$item->public_id}", ['revision' => revisionId($item), 'image' => postImage(rgb: [200, 0, 0])])->assertSessionHas('success');

    $revisions = $item->refresh()->revisions()->with('asset')->get();
    expect($revisions)->toHaveCount(2)
        ->and($revisions[1]->asset?->checksum_sha256)->toBe($before)
        ->and($revisions[0]->asset?->checksum_sha256)->not->toBe($before);
});
