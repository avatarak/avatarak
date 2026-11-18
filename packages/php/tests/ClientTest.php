<?php

namespace Avatarak\Tests;

use Avatarak\Client;
use Avatarak\Exceptions\ApiException;
use Avatarak\Exceptions\AuthenticationException;
use Avatarak\Exceptions\RateLimitException;
use Avatarak\Profile;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    /** @var list<array{string,string,array,?string}> */
    private array $calls = [];

    private function client(array|callable $reply, ?string $key = 'avk_test'): Client
    {
        return new Client($key, 'https://avatarak.test/', 5, function (string $m, string $u, array $h, ?string $b) use ($reply) {
            $this->calls[] = [$m, $u, $h, $b];

            return is_callable($reply) ? $reply($m, $u, $h, $b) : $reply;
        });
    }

    private static function profileData(string $hash, array $over = []): array
    {
        return $over + [
            'hash' => $hash, 'username' => 'ali', 'profile_url' => 'https://avatarak.test/ali', 'name_fa' => 'علی', 'name_en' => 'Ali',
            'headline' => 'Dev', 'location' => 'Tehran', 'bio' => 'hi', 'website' => 'https://ali.ir', 'socials' => ['github' => 'ali'],
            'links' => [['label' => 'Blog', 'url' => 'https://ali.ir/blog']], 'verified_accounts' => [['network' => 'github', 'identifier' => 'ali', 'url' => 'https://github.com/ali']],
            'avatar_url' => 'https://avatarak.test/avatar/'.$hash,
        ];
    }

    public function test_hash_normalises_email_and_passes_hashes_through(): void
    {
        $h = hash('sha256', 'ali@example.com');
        $this->assertSame($h, Client::hash('  Ali@Example.COM '));
        $this->assertSame($h, Client::hash(strtoupper($h)));
        $this->assertSame('84059b07d4be67b806386c0aad8070a23f18836bbaae342275dc0a83414c32ee', Client::hash('MyEmailAddress@example.com '));
    }

    public function test_avatar_url_needs_no_key_and_no_request(): void
    {
        $c = $this->client([200, [], '{}'], null);
        $h = hash('sha256', 'ali@example.com');
        $this->assertSame("https://avatarak.test/avatar/$h", $c->avatarUrl('ali@example.com'));
        $this->assertSame("https://avatarak.test/avatar/$h?s=96&d=identicon", $c->avatarUrl('ALI@example.com', 96, 'identicon'));
        $this->assertSame("https://avatarak.test/avatar/$h?s=48", $c->avatarUrl($h, 48, 'mp'));
        $this->assertSame([], $this->calls);
    }

    public function test_profile_sends_only_the_hash_and_the_key_and_maps_every_field(): void
    {
        $h = hash('sha256', 'ali@example.com');
        $p = $this->client([200, [], json_encode(['data' => self::profileData($h)])])->profile('Ali@example.com');

        [$m, $u, $headers, $body] = $this->calls[0];
        $this->assertSame(['GET', "https://avatarak.test/api/v1/profiles/$h", null], [$m, $u, $body]);
        $this->assertSame('Bearer avk_test', $headers['Authorization']);
        $this->assertStringNotContainsString('ali@example.com', $u.json_encode($headers), 'the address never leaves the server');

        $this->assertInstanceOf(Profile::class, $p);
        $this->assertSame(['ali', 'علی', 'Dev', 'Tehran'], [$p->username, $p->nameFa, $p->headline, $p->location]);
        $this->assertSame('علی', $p->displayName());
        $this->assertSame(['github' => 'ali'], $p->socials);
        $this->assertSame('Blog', $p->links[0]['label']);
        $this->assertTrue($p->isVerified('github'));
        $this->assertFalse($p->isVerified('telegram'));
        $this->assertSame("https://avatarak.test/avatar/$h", $p->avatarUrl);
    }

    public function test_display_name_falls_back(): void
    {
        $h = str_repeat('a', 64);
        $p = Profile::fromArray(self::profileData($h, ['name_fa' => null]));
        $this->assertSame('Ali', $p->displayName());
        $this->assertSame('ali', Profile::fromArray(self::profileData($h, ['name_fa' => null, 'name_en' => null]))->displayName());
    }

    public function test_unknown_profile_is_null_not_an_exception(): void
    {
        $this->assertNull($this->client([404, [], '{"message":"Profile not found."}'])->profile('nobody@example.com'));
    }

    public function test_errors_are_typed(): void
    {
        try {
            $this->client([401, [], '{"message":"Invalid or missing API key."}'])->profile('a@b.ir');
            $this->fail();
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->status);
            $this->assertSame('Invalid or missing API key.', $e->getMessage());
        }

        try {
            $this->client([429, ['Retry-After' => '17'], '{"message":"Rate limit exceeded."}'])->profile('a@b.ir');
            $this->fail();
        } catch (RateLimitException $e) {
            $this->assertSame(17, $e->retryAfter);
        }

        $this->expectException(ApiException::class);
        $this->client([500, [], 'not json'])->profile('a@b.ir');
    }

    public function test_missing_key_fails_before_any_request(): void
    {
        $c = $this->client([200, [], '{}'], '');
        try {
            $c->profile('a@b.ir');
            $this->fail();
        } catch (AuthenticationException) {
            $this->assertSame([], $this->calls);
        }
    }

    public function test_lookup_dedupes_batches_by_100_and_keeps_unknowns_as_null(): void
    {
        $emails = [];
        for ($i = 0; $i < 230; $i++) {
            $emails[] = "user$i@example.com";
        }
        $emails[] = 'USER0@example.com';             // duplicate after normalising

        $known = Client::hash('user5@example.com');
        $c = $this->client(function ($m, $u, $h, $b) use ($known) {
            $hashes = json_decode($b, true)['hashes'];
            $data = [];
            foreach ($hashes as $x) {
                $data[$x] = $x === $known ? self::profileData($x) : null;
            }

            return [200, [], json_encode(['data' => $data])];
        });

        $out = $c->lookup($emails);

        $this->assertCount(230, $out);
        $this->assertCount(3, $this->calls, '230 hashes = 100 + 100 + 30');
        $this->assertSame([100, 100, 30], array_map(fn ($call) => count(json_decode($call[3], true)['hashes']), $this->calls));
        $this->assertInstanceOf(Profile::class, $out[$known]);
        $this->assertNull($out[Client::hash('user6@example.com')]);
        $this->assertSame('POST', $this->calls[0][0]);
        $this->assertSame('application/json', $this->calls[0][2]['Content-Type']);
    }

    public function test_lookup_of_nothing_makes_no_request(): void
    {
        $this->assertSame([], $this->client([200, [], '{}'])->lookup([]));
        $this->assertSame([], $this->calls);
    }

    public function test_default_transport_without_curl_or_network_fails_cleanly(): void
    {
        $c = new Client('avk_x', 'http://127.0.0.1:9', 1);
        $this->expectException(ApiException::class);
        $c->profile('a@b.ir');
    }
}
