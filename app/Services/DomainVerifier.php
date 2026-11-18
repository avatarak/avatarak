<?php

namespace App\Services;

use App\Models\CustomDomain;
use Closure;

/**
 * A customer proves they control a domain with a TXT record (_avatarak.<domain> = avatarak-domain=<token>), and we also
 * tell them whether the domain already points at us (CNAME or A record), which is what makes the page load.
 */
class DomainVerifier
{
    /** Test seams: fn(string $host): list<string> TXT values / list<string> CNAME targets / list<string> A addresses */
    public ?Closure $txtResolver = null;

    public ?Closure $cnameResolver = null;

    public ?Closure $aResolver = null;

    /** "https://www.My-Site.com/path" -> "my-site.com"; null for anything that is not a plain public domain we may serve. */
    public static function normalize(string $input): ?string
    {
        $host = WebsiteVerifier::normalizeHost($input);
        if (! $host) {
            return null;
        }
        $own = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $own = preg_replace('/^www\./', '', $own);
        // never our own domain or anything under it: that would let someone serve pages from our brand's names
        if ($host === $own || str_ends_with($host, '.'.$own) || str_ends_with($own, '.'.$host)) {
            return null;
        }
        if (preg_match('/\.(local|localhost|internal|test|invalid|example|onion)$/', $host)) {
            return null;
        }

        return $host;
    }

    public function instructions(CustomDomain $d): array
    {
        return [
            'host' => $d->host, 'token' => $d->token,
            'txt' => ['name' => '_avatarak.'.$d->host, 'type' => 'TXT', 'value' => 'avatarak-domain='.$d->token],
            'route' => ['type' => 'CNAME', 'name' => $d->host, 'value' => (string) config('domains.target')],
        ];
    }

    /** @return array{verified:bool,pointed:bool,error?:string} */
    public function check(CustomDomain $d): array
    {
        if ($d->checks >= (int) config('domains.max_checks')) {
            return ['verified' => false, 'pointed' => false, 'error' => 'too_many_attempts'];
        }
        $d->forceFill(['checks' => $d->checks + 1, 'last_checked_at' => now()])->save();

        $txt = $this->txtResolver ? ($this->txtResolver)('_avatarak.'.$d->host) : array_map(fn ($r) => $r['txt'] ?? '', @dns_get_record('_avatarak.'.$d->host, DNS_TXT) ?: []);
        $want = 'avatarak-domain='.$d->token;
        $verified = collect($txt)->contains(fn ($v) => hash_equals($want, trim((string) $v)));

        if ($verified && ! $d->isVerified()) {
            $d->forceFill(['verified_at' => now()])->save();
        }

        return ['verified' => $verified || $d->isVerified(), 'pointed' => $this->pointed($d->host)];
    }

    public function pointed(string $host): bool
    {
        $target = strtolower((string) config('domains.target'));
        $cname = $this->cnameResolver ? ($this->cnameResolver)($host) : array_map(fn ($r) => $r['target'] ?? '', @dns_get_record($host, DNS_CNAME) ?: []);
        if (collect($cname)->contains(fn ($c) => rtrim(strtolower((string) $c), '.') === $target)) {
            return true;
        }
        $mine = $this->aResolver ? ($this->aResolver)($target) : array_map(fn ($r) => $r['ip'] ?? '', @dns_get_record($target, DNS_A) ?: []);
        $theirs = $this->aResolver ? ($this->aResolver)($host) : array_map(fn ($r) => $r['ip'] ?? '', @dns_get_record($host, DNS_A) ?: []);

        return $mine !== [] && array_intersect($mine, $theirs) !== [];
    }
}
