<?php

namespace App\Services;

use RuntimeException;

/** Decides whether an address is a safe place for us to POST to (no internal networks, no credentials). */
class WebhookUrl
{
    /** @return array{host:string,port:int,ip:?string} */
    public static function check(string $url): array
    {
        $p = parse_url($url);
        $allowPrivate = (bool) config('webhooks.allow_private');
        if (! $p || empty($p['host']) || ! in_array(strtolower($p['scheme'] ?? ''), $allowPrivate ? ['http', 'https'] : ['https'], true)) {
            throw new RuntimeException('url must be https');
        }
        if (isset($p['user']) || isset($p['pass'])) {
            throw new RuntimeException('credentials in url');
        }
        $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
        $host = trim($p['host'], '[]');
        if ($allowPrivate) {
            return ['host' => $host, 'port' => $port, 'ip' => null];
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if (! $ips) {
            throw new RuntimeException('host does not resolve');
        }
        foreach ($ips as $ip) {
            if (! LinkPreviewService::isPublicIp($ip)) {
                throw new RuntimeException('non-public address');
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $out = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            $out[] = $r['ip'] ?? $r['ipv6'] ?? null;
        }
        if (! array_filter($out)) {
            $out = @gethostbynamel($host) ?: [];
        }

        return array_values(array_filter($out));
    }
}
