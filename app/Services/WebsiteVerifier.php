<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationChallenge;
use App\Models\VerifiedAccount;
use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;

/**
 * Proves control of a domain by one of three standard methods (like Google Search Console):
 *  - meta : <meta name="avatarak-verification" content="TOKEN"> on the home page
 *  - file : /.well-known/avatarak-verification.txt containing TOKEN
 *  - dns  : a TXT record "avatarak-verification=TOKEN" on the domain
 * Fetching uses LinkPreviewService::get(), i.e. the SSRF-hardened client (public IPs only, ports 80/443,
 * revalidated redirects, size/time caps).
 */
class WebsiteVerifier
{
    public const META = 'avatarak-verification';

    /** Test seam: fn(string $host): list<string> of TXT values. */
    public ?Closure $txtResolver = null;

    public function __construct(private LinkPreviewService $http) {}

    /** "https://www.Example.com/x?y" -> "example.com"; null when it is not a plain public hostname. */
    public static function normalizeHost(string $input): ?string
    {
        $input = trim($input);
        $host = parse_url(preg_match('#^[a-z][a-z0-9+.-]*://#i', $input) ? $input : 'https://'.$input, PHP_URL_HOST);
        if (! is_string($host)) {
            return null;
        }
        $host = strtolower(rtrim($host, '.'));
        $host = preg_replace('/^www\./', '', $host);

        if (strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP)
            || ! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
            return null;
        }

        return $host;
    }

    /** What the user has to publish, in each of the three ways. */
    public function instructions(VerificationChallenge $c): array
    {
        return [
            'domain' => $c->identifier,
            'token' => $c->token,
            'meta' => '<meta name="'.self::META.'" content="'.$c->token.'">',
            'file' => ['path' => 'https://'.$c->identifier.'/.well-known/avatarak-verification.txt', 'content' => $c->token],
            'dns' => ['name' => $c->identifier, 'type' => 'TXT', 'value' => self::META.'='.$c->token],
            'expires_at' => $c->expires_at->toIso8601String(),
        ];
    }

    /**
     * @return array{ok:bool, method?:string, error?:string}
     */
    public function check(VerificationChallenge $c): array
    {
        if ($c->expired()) {
            return ['ok' => false, 'error' => 'expired'];
        }
        if ($c->attempts >= config('verification.max_checks_per_challenge')) {
            return ['ok' => false, 'error' => 'too_many_attempts'];
        }
        $c->increment('attempts');

        $method = $this->viaMeta($c) ? 'meta' : ($this->viaFile($c) ? 'file' : ($this->viaDns($c) ? 'dns' : null));
        if (! $method) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        return DB::transaction(function () use ($c, $method) {
            $taken = VerifiedAccount::where('network', 'website')->where('identifier', $c->identifier)
                ->where('user_id', '!=', $c->user_id)->lockForUpdate()->exists();
            if ($taken) {
                return ['ok' => false, 'error' => 'taken'];
            }
            VerifiedAccount::updateOrCreate(
                ['network' => 'website', 'identifier' => $c->identifier],
                ['user_id' => $c->user_id, 'method' => $method, 'verified_at' => now()],
            );
            $c->delete();

            return ['ok' => true, 'method' => $method];
        });
    }

    private function viaMeta(VerificationChallenge $c): bool
    {
        foreach (['https', 'http'] as $scheme) {
            try {
                [$html, $final] = $this->http->get($scheme.'://'.$c->identifier.'/', 524288, ['text/html', 'application/xhtml+xml']);
            } catch (\Throwable) {
                continue;
            }
            if (self::normalizeHost($final) !== $c->identifier) {
                continue;                      // redirected to somebody else's domain: proves nothing
            }
            $dom = new DOMDocument;
            $prev = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $nodes = (new DOMXPath($dom))->query("//meta[@name='".self::META."']/@content");
            foreach ($nodes ?: [] as $n) {
                if (hash_equals($c->token, trim($n->nodeValue))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function viaFile(VerificationChallenge $c): bool
    {
        foreach (['https', 'http'] as $scheme) {
            try {
                [$body, $final] = $this->http->get($scheme.'://'.$c->identifier.'/.well-known/avatarak-verification.txt', 4096, ['text/']);
            } catch (\Throwable) {
                continue;
            }
            if (self::normalizeHost($final) === $c->identifier && hash_equals($c->token, trim($body))) {
                return true;
            }
        }

        return false;
    }

    private function viaDns(VerificationChallenge $c): bool
    {
        $values = $this->txtResolver
            ? ($this->txtResolver)($c->identifier)
            : array_map(fn ($r) => $r['txt'] ?? '', @dns_get_record($c->identifier, DNS_TXT) ?: []);

        foreach ($values as $v) {
            if (hash_equals(self::META.'='.$c->token, trim((string) $v))) {
                return true;
            }
        }

        return false;
    }
}
