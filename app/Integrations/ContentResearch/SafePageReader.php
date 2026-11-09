<?php

declare(strict_types=1);

namespace App\Integrations\ContentResearch;

use App\Domain\Content\Contracts\PageReader;
use App\Domain\Content\Research\SearchFailed;
use App\Domain\Content\Research\UrlPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Reads a page title for a manual source with SSRF protection (ADR-0011, phase 4):
 * the URL policy first; every resolved address must be public; the connection is pinned to the checked address
 * (no DNS rebinding between check and connect); redirects are followed by hand, a few at most, each checked again;
 * HTML only; time and size bounded; no cookies or credentials sent.
 */
final class SafePageReader implements PageReader
{
    public function __construct(private readonly UrlPolicy $policy, private readonly HostResolver $resolver) {}

    public function read(string $url): array
    {
        $current = $url;
        for ($hop = 0; $hop <= (int) config('content.reader.max_redirects'); $hop++) {
            $refusal = $this->policy->refusal($current);
            if ($refusal !== null) {
                throw new SearchFailed('blocked', $refusal);
            }
            $parts = (array) parse_url($current);
            $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
            $port = (int) ($parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80));
            $addresses = $this->resolver->resolve($host);
            if ($addresses === []) {
                throw new SearchFailed('unreachable', 'Alamat tidak ditemukan.');
            }
            foreach ($addresses as $address) {
                if (! UrlPolicy::isPublicIp($address)) {
                    throw new SearchFailed('blocked', 'Alamat mengarah ke jaringan privat atau lokal.');
                }
            }
            $pinned = str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];

            try {
                $response = Http::withoutRedirecting()
                    ->timeout((int) config('content.reader.timeout_seconds'))
                    ->withHeaders(['User-Agent' => 'PrasetyaPlatform-ContentStudio/1.0 (+title reader)', 'Accept' => 'text/html'])
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"]], 'stream' => true])
                    ->get($current);
            } catch (ConnectionException) {
                throw new SearchFailed('unreachable', 'Halaman tidak dapat dibuka atau tidak menjawab tepat waktu.');
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location === '') {
                    throw new SearchFailed('bad_page', 'Halaman mengalihkan tanpa tujuan.');
                }
                $current = $this->absolute($current, $location);

                continue;
            }
            if (! $response->successful()) {
                throw new SearchFailed('bad_page', "Halaman menjawab dengan status {$response->status()}.");
            }
            if (! str_contains(strtolower($response->header('Content-Type')), 'text/html')) {
                throw new SearchFailed('bad_page', 'Hanya halaman HTML yang dapat dibaca.');
            }

            $html = $this->body($response->toPsrResponse()->getBody(), (int) config('content.reader.max_bytes'));

            return ['title' => $this->title($html), 'description' => $this->description($html), 'url' => $current];
        }

        throw new SearchFailed('bad_page', 'Terlalu banyak pengalihan halaman.');
    }

    /** At most $max bytes of the body, read in chunks so a large page is never held whole. */
    private function body(StreamInterface $stream, int $max): string
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $html = '';
        while (! $stream->eof() && strlen($html) < $max) {
            $chunk = $stream->read(min(8192, $max - strlen($html)));
            if ($chunk === '') {
                break;
            }
            $html .= $chunk;
        }

        return $html;
    }

    private function title(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]*content=["\']([^"\']+)/i', $html, $m) === 1 || preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function description(string $html): ?string
    {
        return preg_match('/<meta[^>]+(?:name|property)=["\'](?:og:)?description["\'][^>]*content=["\']([^"\']+)/i', $html, $m) === 1 ? $m[1] : null;
    }

    private function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $parts = (array) parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return str_starts_with($location, '/') ? $origin.$location : $origin.'/'.ltrim($location, './');
    }
}
