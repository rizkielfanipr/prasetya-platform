<?php

declare(strict_types=1);

use App\Domain\Content\Ai\BrandKnowledgeAssistantPrompt;
use App\Domain\Content\Models\ContentBrand;

it('asks the provider for an operational guide without inventing or activating ambiguous facts', function (): void {
    $brand = new ContentBrand(['name' => 'Prasetya Webworks', 'code' => 'WEBWORKS']);
    $prompt = app(BrandKnowledgeAssistantPrompt::class)->build($brand, [
        'offers' => 'Paket 1 harga 200 dan Paket 2 harga 300.',
        'voice' => 'Ramah dan agak lucu.',
    ], null);

    expect($prompt->version)->toBe('brand-knowledge-v2')
        ->and($prompt->system)->toContain('bukan sekadar menyalin atau meringkas jawaban')
        ->and($prompt->system)->toContain('jangan salin angkanya ke mana pun')
        ->and($prompt->system)->toContain('## Informasi yang Perlu Dikonfirmasi')
        ->and($prompt->user)->toContain('Produk, layanan, harga, dan ketentuan')
        ->and($prompt->user)->toContain('Ramah dan agak lucu.');
});
