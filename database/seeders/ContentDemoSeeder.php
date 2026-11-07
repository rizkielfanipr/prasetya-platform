<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\ReviewContent;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Actions\SubmitContentForReview;
use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoImage;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One week of Prasetya Webworks posts in every state, built through the real actions: demo writes and submits, demo2
 * reviews (four-eyes). Local and testing only; the copy is illustrative and states no real price or promise.
 */
class ContentDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing', 'e2e')) {
            throw new RuntimeException('Demo content is for local and testing environments only.');
        }
        $brand = ContentBrand::query()->where('code', 'WEBWORKS')->first();
        $writer = User::query()->where('username', 'demo')->first();
        $reviewer = User::query()->where('username', 'demo2')->first();
        if ($brand === null || $writer === null || $reviewer === null || ContentItem::query()->exists()) {
            return;
        }

        $monday = CarbonImmutable::now(BusinessTime::zone())->startOfWeek(CarbonImmutable::MONDAY)->setTime(10, 0);
        $posts = [
            ['Kenapa website lambat merugikan bisnis', 0, 'published', [42, 157, 143]],
            ['3 tanda website Anda perlu diperbarui', 1, 'approved', [233, 196, 106]],
            ['Cerita klien: toko roti go online', 2, 'review', [244, 162, 97]],
            ['Checklist sebelum meluncurkan website', 3, 'revision', [231, 111, 81]],
            ['Domain, hosting, dan SSL dalam satu menit', 4, 'draft', null],
            ['Tanya jawab: berapa lama membuat website?', 5, 'empty', null],
        ];

        foreach ($posts as [$title, $day, $state, $colour]) {
            $item = app(CreateContentItem::class)($brand, $title, $monday->addDays($day)->utc(), $writer);
            if ($state === 'empty') {
                continue;
            }

            $image = $colour === null ? null : ['path' => DemoImage::file(1080, 1350, $colour), 'name' => 'demo.png'];
            $revision = app(SaveContentRevision::class)($item, (string) $item->activeRevision?->public_id, [
                'objective' => 'Edukasi pemilik usaha kecil tentang manfaat website yang cepat dan rapi.',
                'audience' => 'Pemilik UMKM yang baru mulai berjualan online.',
                'brief' => "Satu gambar 4:5 dengan judul besar \"{$title}\" dan satu poin utama.",
                'caption' => "{$title}.\n\nWebsite yang rapi membantu calon pelanggan percaya sebelum menghubungi Anda. Tanyakan kebutuhan Anda lewat DM.",
                'hashtags' => '#prasetyawebworks #websiteumkm #bisnisonline',
                'alt_text' => "Kartu berwarna dengan tulisan {$title}.",
                'visual_direction' => 'Latar polos warna brand, tipografi tebal, logo kecil di pojok bawah.',
            ], $image, $writer);
            if ($image !== null) {
                @unlink($image['path']);
            }
            if ($state === 'draft') {
                continue;
            }

            app(SubmitContentForReview::class)($item, $revision->public_id, $writer);
            match ($state) {
                'revision' => app(ReviewContent::class)($item, $revision->public_id, ContentDecision::RevisionRequested, 'Poin utama terlalu panjang; ringkas jadi satu kalimat.', $reviewer),
                'approved', 'published' => app(ReviewContent::class)($item, $revision->public_id, ContentDecision::Approved, null, $reviewer),
                default => null,
            };
            if ($state === 'published') {
                app(MarkContentPublished::class)($item, $revision->public_id, CarbonImmutable::now(), null, 'Diposting manual dari akun Instagram brand.', $writer);
            }
        }
    }
}
