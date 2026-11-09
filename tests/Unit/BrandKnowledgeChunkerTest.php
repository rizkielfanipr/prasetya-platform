<?php

declare(strict_types=1);

use App\Domain\Content\Support\BrandKnowledgeChunker;

it('turns paragraphs into stable searchable chunks', function (): void {
    $source = "Tentang brand\r\nKami membantu UMKM.\r\n\r\nHarga\r\nPaket mulai Rp3.500.000.";

    expect(app(BrandKnowledgeChunker::class)->chunks($source))->toBe([
        "Tentang brand\nKami membantu UMKM.",
        "Harga\nPaket mulai Rp3.500.000.",
    ]);
});

it('splits a long paragraph without losing its content', function (): void {
    $source = implode(' ', array_fill(0, 300, 'knowledge'));
    $chunks = app(BrandKnowledgeChunker::class)->chunks($source);

    expect($chunks)->toHaveCount(3)
        ->and(implode(' ', $chunks))->toBe($source)
        ->and(max(array_map(mb_strlen(...), $chunks)))->toBeLessThanOrEqual(1200);
});

it('keeps open questions in the document but excludes them from retrieval chunks', function (): void {
    $source = "# Brand\n\n## Produk dan Layanan\nWebsite untuk UMKM.\n\n## Informasi yang Perlu Dikonfirmasi\n- Mata uang harga paket\n- Isi setiap paket";

    expect(app(BrandKnowledgeChunker::class)->chunks($source))->toBe([
        '# Brand',
        "## Produk dan Layanan\nWebsite untuk UMKM.",
    ]);
});
