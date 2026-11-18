<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\VerifiedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkedInVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'ali'): User
    {
        $u = User::factory()->create();
        Profile::create(['user_id' => $u->id, 'username' => $name, 'socials' => ['linkedin' => 'someone-else']]);

        return $u;
    }

    private function on(): void
    {
        config(['verification.linkedin.client_id' => 'cid', 'verification.linkedin.client_secret' => 'sec']);
    }

    private function start(User $u): string
    {
        $loc = $this->actingAs($u)->get('/account/verifications/linkedin/redirect')->assertRedirect()->headers->get('Location');
        parse_str(parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertStringStartsWith('https://www.linkedin.com/oauth/v2/authorization', $loc);
        $this->assertSame(['code', 'cid', route('verify.linkedin.callback'), 'openid profile'], [$q['response_type'], $q['client_id'], $q['redirect_uri'], $q['scope']]);

        return $q['state'];
    }

    public function test_it_is_off_until_configured(): void
    {
        $u = $this->user();
        $this->actingAs($u)->getJson('/account/verifications')->assertJsonPath('linkedin.enabled', false);
        $this->get('/account/verifications/linkedin/redirect')->assertStatus(503);
        $this->get('/account/verifications/linkedin/callback?code=x&state=y')->assertStatus(503);
    }

    public function test_the_flow_stores_the_stable_id_and_the_name_and_makes_no_link(): void
    {
        $this->on();
        $u = $this->user();
        $state = $this->start($u);
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'tok']),
            'api.linkedin.com/v2/userinfo' => Http::response(['sub' => 'abC123_xyz', 'name' => 'Ali Rezaei', 'picture' => 'https://x/y.jpg', 'email' => 'secret@x.ir']),
        ]);

        $this->get('/account/verifications/linkedin/callback?code=abc&state='.$state)->assertRedirect('/panel?verify=linkedin-ok');

        $a = VerifiedAccount::first();
        $this->assertSame(['linkedin', 'abC123_xyz', 'Ali Rezaei', 'abC123_xyz', 'linkedin_oauth'], [$a->network, $a->identifier, $a->label, $a->external_id, $a->method]);
        $this->assertNull($a->url(), 'LinkedIn gives no public address, and the handle in "socials" is the user\'s own claim');

        // the public JSON shows the name, never the opaque id, never the email LinkedIn returned
        $json = $this->getJson('/ali.json')->assertOk();
        $this->assertSame([['network' => 'linkedin', 'identifier' => 'Ali Rezaei', 'url' => null]], $json->json('verified_accounts'));
        $this->assertStringNotContainsString('secret@x.ir', $json->getContent());
        $this->assertStringNotContainsString('abC123_xyz', $json->getContent());
        $this->get('/ali')->assertOk()->assertSee('LinkedIn')->assertSee('Ali Rezaei');
        $this->get('/ali.md')->assertSee('Linkedin: Ali Rezaei', false);
    }

    public function test_a_wrong_state_a_failed_exchange_and_bad_ids_verify_nothing(): void
    {
        $this->on();
        $u = $this->user();
        $this->start($u);
        $this->get('/account/verifications/linkedin/callback?code=abc&state=forged')->assertRedirect('/panel?verify=linkedin-linkedin_failed');

        foreach ([Http::response(['error' => 'invalid_grant'], 400)] as $r) {
            $state = $this->start($u);
            Http::fake(['www.linkedin.com/*' => $r]);
            $this->get('/account/verifications/linkedin/callback?code=abc&state='.$state)->assertRedirect('/panel?verify=linkedin-linkedin_failed');
        }
        foreach ([['sub' => ''], ['sub' => 'has space'], ['name' => 'no sub'], ['sub' => str_repeat('a', 101)]] as $info) {
            $state = $this->start($u);
            Http::fake(['www.linkedin.com/*' => Http::response(['access_token' => 't']), 'api.linkedin.com/*' => Http::response($info)]);
            $this->get('/account/verifications/linkedin/callback?code=abc&state='.$state)->assertRedirect('/panel?verify=linkedin-linkedin_failed');
        }
        $this->assertSame(0, VerifiedAccount::count());
    }

    public function test_one_linkedin_account_belongs_to_one_user_and_reverifying_replaces(): void
    {
        $this->on();
        $a = $this->user('first');
        $b = $this->user('second');
        $who = ['sub' => '', 'name' => ''];
        Http::fake(['www.linkedin.com/*' => Http::response(['access_token' => 't']), 'api.linkedin.com/*' => function () use (&$who) { return Http::response($who); }]);
        $fake = function (string $sub, string $name) use (&$who) {
            $who = ['sub' => $sub, 'name' => $name];
        };

        $fake('same-sub-1', 'Ali');
        $this->get('/account/verifications/linkedin/callback?code=x&state='.$this->start($a))->assertRedirect('/panel?verify=linkedin-ok');

        $fake('same-sub-1', 'Ali');                                                       // somebody else proving the same LinkedIn account
        $this->get('/account/verifications/linkedin/callback?code=x&state='.$this->start($b))->assertRedirect('/panel?verify=linkedin-taken');
        $this->assertSame($a->id, VerifiedAccount::first()->user_id);

        $fake('other-sub-2', 'Ali Two');                                                  // the first user switches LinkedIn accounts
        $this->get('/account/verifications/linkedin/callback?code=x&state='.$this->start($a))->assertRedirect('/panel?verify=linkedin-ok');
        $this->assertSame(['other-sub-2'], VerifiedAccount::pluck('identifier')->all());
    }

    public function test_admins_can_filter_by_the_new_network(): void
    {
        $u = $this->user();
        VerifiedAccount::create(['user_id' => $u->id, 'network' => 'linkedin', 'identifier' => 'sub1', 'label' => 'Ali', 'external_id' => 'sub1', 'method' => 'linkedin_oauth', 'verified_at' => now()]);
        $admin = $this->user('boss');
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->getJson('/admin/api/verified?network=linkedin')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.url', null);
    }
}
