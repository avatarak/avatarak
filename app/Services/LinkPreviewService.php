<?php

namespace App\Services;

use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fetches a link's preview image (og:image / twitter:image) and stores a re-encoded thumbnail on our disk,
 * so visitors never contact the third-party site.
 *
 * Because the server opens a URL chosen by a user, this is an SSRF hot spot. Defences:
 *  - http/https only, no credentials in the URL, only ports 80/443
 *  - every hostname is resolved and ALL its addresses must be public (no loopback, private, link-local,
 *    CGNAT, reserved, IPv4-mapped IPv6 of those)
 *  - redirects are followed manually (max 3) and re-validated at every hop
 *  - we connect to the validated IP (CURLOPT_RESOLVE) so DNS cannot change between check and use
 *  - hard timeout, hard download caps (aborts mid-transfer), and the image is decoded and re-encoded with GD
 */
class LinkPreviewService
{
    /** Test seam: fn(string $host): list<string> of IPs. */
    public ?Closure $resolver = null;

    public static function key(string $url): string
    {
        return sha1($url);
    }

    public static function path(string $key): string
    {
        return "link-thumbs/{$key}.jpg";
    }

    /** Fetch + store. Returns true when a thumbnail now exists for $url. */
    public function fetch(string $url): bool
    {
        try {
            [$html, $finalUrl] = $this->get($url, (int) config('linkpreview.max_html_bytes'), ['text/html', 'application/xhtml+xml']);
            $imageUrl = $this->imageUrlFrom($html, $finalUrl);
            if (! $imageUrl) {
                return false;
            }
            [$bytes] = $this->get($imageUrl, (int) config('linkpreview.max_image_bytes'), ['image/']);

            return $this->store($url, $bytes);
        } catch (\Throwable $e) {
            logger()->info('link preview failed', ['url' => $url, 'reason' => $e->getMessage()]);

            return false;
        }
    }

    public function imageUrlFrom(string $html, string $baseUrl): ?string
    {
        $dom = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.substr($html, 0, (int) config('linkpreview.max_html_bytes')));
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xp = new DOMXPath($dom);
        foreach (['og:image:secure_url', 'og:image:url', 'og:image', 'twitter:image', 'twitter:image:src'] as $name) {
            $nodes = $xp->query("//meta[@property='{$name}' or @name='{$name}']/@content");
            $value = $nodes && $nodes->length ? trim($nodes->item(0)->nodeValue) : '';
            if ($value !== '') {
                return $this->absolutize($value, $baseUrl);
            }
        }

        return null;
    }

    public function absolutize(string $ref, string $base): ?string
    {
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        $b = parse_url($base);
        if (! $b || empty($b['host']) || str_contains($ref, ':')) {
            return null;                       // data:, javascript:, etc.
        }
        $origin = ($b['scheme'] ?? 'https').'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($ref, '//')) {
            return ($b['scheme'] ?? 'https').':'.$ref;
        }
        if (str_starts_with($ref, '/')) {
            return $origin.$ref;
        }
        $dir = rtrim(dirname($b['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$ref;
    }

    /** Decode, cover-crop to the thumbnail ratio, re-encode as JPEG and store. */
    public function store(string $url, string $bytes): bool
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return false;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        if ($w < 120 || $h < 60) {
            return false;                      // favicons / tracking pixels
        }

        $tw = (int) config('linkpreview.thumb_width');
        $th = (int) config('linkpreview.thumb_height');
        $scale = max($tw / $w, $th / $h);
        $cw = (int) round($tw / $scale);
        $ch = (int) round($th / $scale);

        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $cw, 2), intdiv($h - $ch, 2), $tw, $th, $cw, $ch);

        ob_start();
        imagejpeg($dst, null, 82);
        $jpeg = ob_get_clean();

        return Storage::disk(config('avatar.disk'))->put(self::path(self::key($url)), $jpeg);
    }

    /**
     * GET with manual, re-validated redirects and a hard size cap.
     *
     * @param  list<string>  $types  accepted content-type prefixes
     * @return array{0:string,1:string} [body, final url]
     */
    private function get(string $url, int $maxBytes, array $types): array
    {
        $max = (int) config('linkpreview.max_redirects');

        for ($hop = 0; $hop <= $max; $hop++) {
            [$host, $port, $ip] = $this->validate($url);

            $options = [
                'allow_redirects' => false,
                'progress' => function ($total, $downloaded) use ($maxBytes) {
                    if ($downloaded > $maxBytes) {
                        throw new RuntimeException('response too large');
                    }
                },
            ];
            if (config('linkpreview.pin_dns') && $ip) {
                $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]];
            }

            $res = Http::timeout((int) config('linkpreview.timeout'))->connectTimeout(4)
                ->withHeaders(['User-Agent' => 'IravatarLinkPreview/1.0', 'Accept' => 'text/html,image/*;q=0.8'])
                ->withOptions($options)->get($url);

            if ($res->redirect()) {
                $loc = $res->header('Location');
                $next = $loc ? $this->absolutize($loc, $url) : null;
                if (! $next) {
                    throw new RuntimeException('bad redirect');
                }
                $url = $next;

                continue;
            }
            if (! $res->successful()) {
                throw new RuntimeException('http '.$res->status());
            }

            return [$this->body($res, $maxBytes, $types), $url];
        }

        throw new RuntimeException('too many redirects');
    }

    private function body(Response $res, int $maxBytes, array $types): string
    {
        $ct = strtolower($res->header('Content-Type'));
        $ok = false;
        foreach ($types as $t) {
            $ok = $ok || str_starts_with($ct, $t);
        }
        if (! $ok) {
            throw new RuntimeException("unexpected content type [{$ct}]");
        }
        $body = $res->body();
        if (strlen($body) > $maxBytes) {
            throw new RuntimeException('response too large');
        }

        return $body;
    }

    /**
     * @return array{0:string,1:int,2:?string} [host, port, validated ip]
     */
    public function validate(string $url): array
    {
        $p = parse_url($url);
        if (! $p || empty($p['host']) || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new RuntimeException('unsupported url');
        }
        if (isset($p['user']) || isset($p['pass'])) {
            throw new RuntimeException('credentials in url');
        }
        $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
        if (! config('linkpreview.allow_private') && ! in_array($port, [80, 443], true)) {
            throw new RuntimeException('port not allowed');
        }

        $host = trim($p['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);
        if (! $ips) {
            throw new RuntimeException('host does not resolve');
        }
        if (! config('linkpreview.allow_private')) {
            foreach ($ips as $ip) {
                if (! self::isPublicIp($ip)) {
                    throw new RuntimeException("non-public address [{$ip}]");
                }
            }
        }

        return [$host, $port, $ips[0]];
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return ($this->resolver)($host);
        }
        $out = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            $out[] = $r['ip'] ?? $r['ipv6'] ?? null;
        }
        if (! array_filter($out)) {
            // some resolvers/platforms don't support dns_get_record; fall back to the system resolver (IPv4)
            $out = @gethostbynamel($host) ?: [];
        }

        return array_values(array_filter($out));
    }

    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        // IPv4-mapped IPv6 (::ffff:10.0.0.1) must be judged as the IPv4 address
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            $ip = $m[1];
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $l = ip2long($ip);
            foreach (['100.64.0.0/10', '192.0.0.0/24', '198.18.0.0/15', '192.88.99.0/24'] as $cidr) {       // CGNAT, IETF, benchmarking, 6to4
                [$net, $bits] = explode('/', $cidr);
                if (($l & (-1 << (32 - (int) $bits))) === (ip2long($net) & (-1 << (32 - (int) $bits)))) {
                    return false;
                }
            }
        } elseif (preg_match('/^(fc|fd|fe[89ab])/i', $ip) || $ip === '::1' || $ip === '::') {              // unique-local, link-local
            return false;
        }

        return true;
    }
}
