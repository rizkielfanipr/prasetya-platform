<?php

declare(strict_types=1);

namespace App\Domain\Content\Research;

use App\Domain\Content\Enums\SourceTrust;

/**
 * Which URLs research may keep or fetch, and how far each can be trusted (ADR-0011, phase 4).
 *
 * - Only http(s), default ports, no credentials in the URL.
 * - No IP-literal hosts in private, loopback, link-local, reserved, or metadata ranges; no localhost or internal names.
 *   Fetching also checks the resolved addresses (SafePageReader); this class decides from the URL alone.
 * - Blocked domains are dropped; when an allowlist is configured, only those domains pass.
 * - Canonical form: lower-case host, no fragment, no tracking parameters, so one page is stored once.
 */
final class UrlPolicy
{
    private const array TRACKING_PREFIXES = ['utm_', 'fbclid', 'gclid', 'mc_', 'igshid', 'ref_src'];

    private const array INTERNAL_SUFFIXES = ['localhost', '.local', '.internal', '.localdomain', '.lan', '.home', '.corp', '.intranet'];

    /** Why this URL may not be used, or null. */
    public function refusal(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return 'URL tidak valid.';
        }
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return 'Hanya alamat http atau https yang boleh dipakai.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'URL tidak boleh berisi nama pengguna atau kata sandi.';
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            return 'Hanya port standar (80 atau 443) yang boleh dipakai.';
        }
        $host = strtolower(trim($parts['host'], '[].'));
        if ($host === '' || ! str_contains($host, '.') && filter_var($host, FILTER_VALIDATE_IP) === false) {
            return 'Alamat internal tidak boleh dipakai.';
        }
        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                return 'Alamat internal tidak boleh dipakai.';
            }
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false && ! self::isPublicIp($host)) {
            return 'Alamat jaringan privat atau lokal tidak boleh dipakai.';
        }
        if ($this->matches($host, (array) config('content.research.blocked_domains'))) {
            return 'Domain ini diblokir untuk riset.';
        }
        $allowed = (array) config('content.research.allowed_domains');
        if ($allowed !== [] && ! $this->matches($host, $allowed)) {
            return 'Domain ini tidak ada di daftar domain riset yang diizinkan.';
        }

        return null;
    }

    /** True for a routable public address; false for private, loopback, link-local, reserved, and metadata ranges. */
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        // Ranges PHP's flags miss: carrier-grade NAT, benchmarking, IPv4-mapped IPv6, unique-local IPv6.
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            $long = (int) sprintf('%u', ip2long($ip));

            return ! self::inRange($long, '100.64.0.0', 10) && ! self::inRange($long, '198.18.0.0', 15) && ! self::inRange($long, '0.0.0.0', 8);
        }
        $hex = bin2hex($packed);

        return ! str_starts_with($hex, '00000000000000000000ffff') && ! str_starts_with($hex, 'fc') && ! str_starts_with($hex, 'fd') && ! str_starts_with($hex, 'fe8');
    }

    public function canonical(string $url): string
    {
        $parts = (array) parse_url(trim($url));
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '/');
        $query = '';
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $params);
            $kept = array_filter($params, static fn (string|int $key): bool => ! array_any(self::TRACKING_PREFIXES, static fn (string $p): bool => str_starts_with(strtolower((string) $key), $p)), ARRAY_FILTER_USE_KEY);
            ksort($kept);
            $query = $kept === [] ? '' : '?'.http_build_query($kept);
        }

        return "{$scheme}://{$host}".($path === '' ? '/' : $path).$query;
    }

    /** The key one page is stored under: canonical URL with a leading "www." ignored. */
    public function identity(string $url): string
    {
        return hash('sha256', (string) preg_replace('#^(https?://)www\.#i', '$1', $this->canonical($url)));
    }

    public function domain(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public function trust(string $url): SourceTrust
    {
        $domain = $this->domain($url);
        if ($this->matches($domain, (array) config('content.research.official_suffixes'))) {
            return SourceTrust::Official;
        }
        if ($this->matches($domain, (array) config('content.research.user_generated_domains'))) {
            return SourceTrust::UserGenerated;
        }

        return SourceTrust::General;
    }

    /** Untrusted page text made safe to store and show: no control characters, collapsed spaces, bounded length. */
    public function clean(string $text, int $max): string
    {
        $text = (string) preg_replace('/[^\P{C}\n]/u', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, $max);
    }

    /** @param  list<string>|array<int, mixed>  $domains */
    private function matches(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            $domain = strtolower(trim((string) $domain, " .\t"));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    private static function inRange(int $ip, string $network, int $bits): bool
    {
        $mask = -1 << (32 - $bits);

        return ($ip & $mask) === ((int) sprintf('%u', ip2long($network)) & $mask);
    }
}
