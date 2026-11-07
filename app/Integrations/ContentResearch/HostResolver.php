<?php

declare(strict_types=1);

namespace App\Integrations\ContentResearch;

/** Resolves a host to its IPv4 and IPv6 addresses. Swappable in tests so they never touch DNS. */
class HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }
        $v4 = gethostbynamel($host) ?: [];
        $v6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique([...$v4, ...array_filter($v6, 'is_string')]));
    }
}
