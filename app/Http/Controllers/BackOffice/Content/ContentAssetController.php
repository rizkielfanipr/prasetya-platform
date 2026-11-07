<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Content;

use App\Domain\Content\Models\ContentAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a post image from private storage to a signed-in user, inline or as a download. */
final class ContentAssetController
{
    public function __invoke(Request $request, ContentAsset $asset): StreamedResponse
    {
        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404);

        return $disk->response($asset->path, $asset->downloadName(), [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
