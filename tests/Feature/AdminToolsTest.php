<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Services\Payment\GatewayManager;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $a = User::factory()->create(['is_admin' => true, 'email' => 'boss@example.ir']);
        Profile::create(['user_id' => $a->id, 'username' => 'boss']);

        return $a;
    }

    private function register(string $n = 'newbie'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/register', ['name' => 'N', 'email' => "$n@example.ir", 'password' => 'secret-pass-1', 'username' => $n, 'accept_terms' => true]);
    }

    public function test_only_admins_reach_the_new_endpoints(): void
    {
        $user = User::factory()->create();
        foreach (['settings', 'announcements', 'export/users.csv'] as $p) {
            $this->getJson("/admin/api/$p")->assertStatus(401);          // signed-out visitors
        }
        foreach (['settings', 'announcements', 'export/users.csv'] as $p) {
            $this->actingAs($user)->getJson("/admin/api/$p")->assertNotFound();      // signed in, but not an administrator
        }
        $this->actingAs($user)->putJson('/admin/api/settings/registration_open', ['value' => false])->assertNotFound();
    }

    public function test_closing_registration_blocks_sign_up_and_reopening_restores_it(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/admin/api/settings/registration_open', ['value' => false])->assertOk();
        auth()->logout();
        $this->register()->assertStatus(403);
        $this->getJson('/site/status')->assertJsonPath('registration_open', false);

        $this->actingAs($admin)->deleteJson('/admin/api/settings/registration_open')->assertOk()->assertJsonPath('settings.0.overridden', false);
        auth()->logout();
        $this->register()->assertCreated();
        $this->assertSame(2, AuditLog::whereIn('action', ['settings.update', 'settings.reset'])->count());
    }

    public function test_settings_validate_and_take_effect_on_config_and_persist_across_requests(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->putJson('/admin/api/settings/billing_gateway', ['value' => 'bitcoin'])->assertStatus(422);
        $this->putJson('/admin/api/settings/registration_open', ['value' => 'maybe'])->assertStatus(422);
        $this->putJson('/admin/api/settings/nope', ['value' => true])->assertNotFound();
        $this->putJson('/admin/api/settings/billing_gateway', ['value' => 'fake'])->assertOk();
        $this->assertSame('fake', config('billing.gateway'));
        $this->assertTrue(GatewayManager::enabled());
        $this->putJson('/admin/api/settings/stats_enabled', ['value' => false])->assertOk();
        $this->assertFalse(config('stats.enabled'));

        // a fresh Settings instance (as in a new request) still sees the stored values
        config(['stats.enabled' => true]);
        app(Settings::class)->apply();
        $this->assertFalse(config('stats.enabled'));
        $this->assertSame('settings.update', AuditLog::latest('id')->first()->action);
    }

    public function test_announcements_crud_window_and_public_status(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $base = ['message_fa' => 'تعمیرات شبانه', 'message_en' => 'Night maintenance', 'level' => 'warning', 'active' => true, 'dismissible' => true];
        $id = $this->postJson('/admin/api/announcements', $base)->assertCreated()->json('id');
        $this->postJson('/admin/api/announcements', ['level' => 'loud'] + $base)->assertStatus(422);
        $this->postJson('/admin/api/announcements', ['starts_at' => '2030-01-02', 'ends_at' => '2030-01-01'] + $base)->assertStatus(422);
        auth()->logout();

        $this->withHeader('X-Locale', 'en')->getJson('/site/status')->assertJsonPath('announcements.0.message', 'Night maintenance')->assertJsonPath('announcements.0.level', 'warning');
        $this->withHeader('X-Locale', 'fa')->getJson('/site/status')->assertJsonPath('announcements.0.message', 'تعمیرات شبانه');

        $this->actingAs($admin)->putJson("/admin/api/announcements/$id", ['active' => false] + $base)->assertOk();
        $this->getJson('/site/status')->assertJsonCount(0, 'announcements');
        $this->putJson("/admin/api/announcements/$id", ['starts_at' => now()->addDay()->toIso8601String(), 'active' => true] + $base)->assertOk()->assertJsonPath('live', false);
        $this->getJson('/site/status')->assertJsonCount(0, 'announcements');
        $this->putJson("/admin/api/announcements/$id", ['ends_at' => now()->subDay()->toIso8601String(), 'starts_at' => now()->subDays(3)->toIso8601String(), 'active' => true] + $base)->assertOk();
        $this->getJson('/site/status')->assertJsonCount(0, 'announcements');

        $this->deleteJson("/admin/api/announcements/$id")->assertOk();
        $this->assertSame(0, Announcement::count());
        $this->assertSame(['announcement.create', 'announcement.update', 'announcement.update', 'announcement.update', 'announcement.delete'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_csv_exports_are_complete_audited_and_formula_safe(): void
    {
        $admin = $this->admin();
        $evil = User::factory()->create(['name' => '=HYPERLINK("http://evil","x")', 'email' => 'e@example.ir']);
        Profile::create(['user_id' => $evil->id, 'username' => 'evil']);
        Payment::create(['user_id' => $evil->id, 'plan' => 'pro', 'period' => 'month', 'amount' => 150000, 'currency' => 'IRT', 'gateway' => 'fake', 'status' => 'paid', 'ref_id' => 'R1', 'authority' => 'A1']);

        $r = $this->actingAs($admin)->get('/admin/api/export/users.csv')->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $body = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);                  // neutralised, still readable
        $this->assertStringNotContainsString(',=HYPERLINK', $body);
        $this->assertStringNotContainsString('"=HYPERLINK', $body);
        $this->assertStringContainsString('boss@example.ir', $body);
        $this->assertStringNotContainsString('$2y$', $body);                      // never a password hash

        $pay = $this->get('/admin/api/export/payments.csv?status=paid')->streamedContent();
        $this->assertStringContainsString('R1', $pay);
        $this->assertStringNotContainsString('card', strtolower(strtok($pay, "\n")));
        $this->get('/admin/api/export/audit.csv')->assertOk();
        $this->get('/admin/api/export/reports.csv')->assertOk();
        $this->get('/admin/api/export/secrets.csv')->assertNotFound();
        $this->assertSame(4, AuditLog::where('action', 'like', 'export.%')->count());
    }

    public function test_cell_neutralises_every_formula_prefix(): void
    {
        foreach (['=1+1', '+1', '-1', '@SUM', "\tx", "\rx"] as $v) {
            $this->assertStringStartsWith("'", \App\Http\Controllers\Admin\ExportController::cell($v));
        }
        $this->assertSame('hello', \App\Http\Controllers\Admin\ExportController::cell('hello'));
        $this->assertSame('', \App\Http\Controllers\Admin\ExportController::cell(null));
        $this->assertSame('1', \App\Http\Controllers\Admin\ExportController::cell(true));
    }
}
