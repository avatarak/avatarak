<?php

namespace Avatarak;

use Avatarak\Exceptions\ApiException;
use Avatarak\Exceptions\AuthenticationException;
use Avatarak\Exceptions\RateLimitException;

/**
 * Client for https://avatarak.ir (or your own install).
 *
 *   $avatarak = new Avatarak\Client('avk_...');
 *   $avatarak->avatarUrl('ali@example.com', size: 96);   // no key needed, no request made
 *   $avatarak->profile('ali@example.com');               // ?Profile, null when there is no public profile
 *   $avatarak->lookup([...many emails...]);              // [hash => ?Profile], batches of 100
 *
 * The email address never leaves your server: only its SHA-256 hash is sent.
 */
class Client
{
    public const MAX_BATCH = 100;

    /** @var callable(string $method, string $url, array<string,string> $headers, ?string $body): array{0:int,1:array<string,string>,2:string} */
    private $transport;

    /**
     * @param  ?callable  $transport  replace the default cURL transport (tests, or your own HTTP client)
     */
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://avatarak.ir',
        private readonly int $timeout = 8,
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? $this->curlTransport(...);
    }

    /** The identifier used everywhere: SHA-256 of the trimmed, lower-cased email. A 64-hex string passes through. */
    public static function hash(string $emailOrHash): string
    {
        $s = trim($emailOrHash);

        return preg_match('/^[a-f0-9]{64}$/i', $s) ? strtolower($s) : hash('sha256', strtolower($s));
    }

    /**
     * Avatar image URL. Needs no API key and makes no request, so it is safe to call in a loop.
     *
     * @param  string  $default  mp | identicon | blank | 404
     */
    public function avatarUrl(string $emailOrHash, ?int $size = null, string $default = 'mp'): string
    {
        $q = array_filter(['s' => $size, 'd' => $default === 'mp' ? null : $default], fn ($v) => $v !== null);

        return rtrim($this->baseUrl, '/').'/avatar/'.self::hash($emailOrHash).($q ? '?'.http_build_query($q) : '');
    }

    /** @return ?Profile null when the address has no public profile (or it is private: the API does not say which) */
    public function profile(string $emailOrHash): ?Profile
    {
        try {
            $r = $this->request('GET', '/api/v1/profiles/'.self::hash($emailOrHash));
        } catch (ApiException $e) {
            if ($e->status === 404) {
                return null;
            }
            throw $e;
        }

        return Profile::fromArray($r['data'] ?? []);
    }

    /**
     * @param  iterable<string>  $emailsOrHashes
     * @return array<string,?Profile> keyed by hash; unknown addresses map to null. Any number of inputs.
     */
    public function lookup(iterable $emailsOrHashes): array
    {
        $hashes = [];
        foreach ($emailsOrHashes as $e) {
            $hashes[self::hash($e)] = true;
        }

        $out = [];
        foreach (array_chunk(array_keys($hashes), self::MAX_BATCH) as $chunk) {
            $r = $this->request('POST', '/api/v1/profiles/lookup', ['hashes' => $chunk]);
            foreach ($chunk as $h) {
                $row = $r['data'][$h] ?? null;
                $out[$h] = is_array($row) ? Profile::fromArray($row) : null;
            }
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $path, ?array $json = null): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new AuthenticationException('An API key is required for profile lookups.', 401);
        }

        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->apiKey, 'User-Agent' => 'avatarak-php/1.0'];
        $body = null;
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($json, JSON_THROW_ON_ERROR);
        }

        [$status, $respHeaders, $raw] = ($this->transport)($method, rtrim($this->baseUrl, '/').$path, $headers, $body);
        $data = json_decode($raw, true);
        $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : "HTTP $status";

        if ($status >= 200 && $status < 300 && is_array($data)) {
            return $data;
        }

        throw match (true) {
            $status === 401 => new AuthenticationException($message, 401, is_array($data) ? $data : null),
            $status === 429 => new RateLimitException($message, 429, is_array($data) ? $data : null, (int) ($this->header($respHeaders, 'retry-after') ?? 60)),
            default => new ApiException($message, $status, is_array($data) ? $data : null),
        };
    }

    /** @param array<string,string> $headers */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === $name) {
                return (string) $v;
            }
        }

        return null;
    }

    /** @return array{0:int,1:array<string,string>,2:string} */
    private function curlTransport(string $method, string $url, array $headers, ?string $body): array
    {
        if (! function_exists('curl_init')) {
            throw new ApiException('ext-curl is required for the default transport; pass your own $transport.');
        }

        $respHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => array_map(fn ($k, $v) => "$k: $v", array_keys($headers), $headers),
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $respHeaders[trim($k)] = trim($v);
                }

                return strlen($line);
            },
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new ApiException('Could not reach the Avatarak API: '.$err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, $respHeaders, (string) $raw];
    }
}
