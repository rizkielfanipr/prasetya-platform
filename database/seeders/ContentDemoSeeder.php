<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Actions\AddContentFact;
use App\Domain\Content\Actions\ArchiveContent;
use App\Domain\Content\Actions\CreateContentItem;
use App\Domain\Content\Actions\MarkContentPublished;
use App\Domain\Content\Actions\MarkContentReady;
use App\Domain\Content\Actions\SaveContentRevision;
use App\Domain\Content\Actions\SavePillar;
use App\Domain\Content\Actions\UpdateBrandProfile;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentPlan;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\DemoImage;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One week of Prasetya Webworks posts in every state (Draft, Ready, Published, Archived), built through the real
 * actions by one operator, demo (ADR-0012). Local and testing only; the copy is illustrative and states no real price
 * or promise.
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
        if ($brand === null || $writer === null || ContentItem::query()->exists()) {
            return;
        }

        // Illustrative rules for local and e2e only. Production gets no rules, pillars, or facts until the owner
        // enters approved ones (ADR-0011, phase 2).
        app(UpdateBrandProfile::class)($brand, [
            'audience' => 'Pemilik UMKM yang baru mulai berjualan online. (Data contoh)',
            'voice' => 'Ramah, jelas, tanpa istilah teknis yang tidak perlu. (Data contoh)',
            'visual_guidance' => 'Latar polos warna brand, tipografi tebal, logo kecil di pojok bawah.',
            'preferred_terms' => ['website rapi', 'usaha Anda'],
            'forbidden_terms' => ['termurah', 'paling murah'],
            'forbidden_claims' => ['dijamin ranking 1'],
            'allowed_ctas' => ['Tanyakan kebutuhan Anda lewat DM'],
            'required_hashtags' => ['#prasetyawebworks'],
            'forbidden_hashtags' => [],
            'approved_examples' => [],
            'rejected_examples' => [],
            'caption_max_length' => 600,
            'hashtag_max' => 10,
            'topic_repeat_days' => 14,
        ], 'Data contoh untuk lokal dan e2e', $writer);
        $pillars = [];
        foreach ([['Edukasi', 50], ['Portofolio', 30], ['Promo', 20]] as [$name, $share]) {
            $pillars[$name] = app(SavePillar::class)($brand, null, $name, 'Data contoh', $share, true, $writer);
        }
        app(AddContentFact::class)($brand, null, 'Contoh fakta: konsultasi awal gratis lewat DM.', 'Data contoh', null, $writer);

        $monday = CarbonImmutable::now(BusinessTime::zone())->startOfWeek(CarbonImmutable::MONDAY)->setTime(10, 0);
        $posts = [
            ['Kenapa website lambat merugikan bisnis', 0, 'published', [42, 157, 143], 'Edukasi'],
            ['3 tanda website Anda perlu diperbarui', 1, 'ready', [233, 196, 106], 'Edukasi'],
            ['Cerita klien: toko roti go online', 2, 'draft', [244, 162, 97], 'Portofolio'],
            ['Checklist sebelum meluncurkan website', 3, 'draft', [231, 111, 81], 'Edukasi'],
            ['Domain, hosting, dan SSL dalam satu menit', 4, 'draft', null, null],
            ['Tanya jawab: berapa lama membuat website?', 5, 'empty', null, null],
            ['Promo lama yang dibatalkan', 6, 'archived', null, null],
        ];

        foreach ($posts as [$title, $day, $state, $colour, $pillar]) {
            $item = app(CreateContentItem::class)($brand, new ContentPlan($title, $monday->addDays($day)->utc(), $pillar === null ? null : $pillars[$pillar]), $writer);
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

            if ($state === 'archived') {
                app(ArchiveContent::class)($item, $revision->public_id, 'Kampanye dibatalkan (data contoh).', $writer);

                continue;
            }
            app(MarkContentReady::class)($item, $revision->public_id, $writer);
            if ($state === 'published') {
                app(MarkContentPublished::class)($item, $revision->public_id, CarbonImmutable::now(), null, 'Diposting manual dari akun Instagram brand.', $writer);
            }
        }
    }
}
