<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A few switches an administrator can flip from the panel without touching .env or redeploying.
 * A stored value overrides the config default at boot (see apply()); "reset" deletes it so .env wins again.
 * Secrets (API keys, merchant ids) never live here: only choices.
 */
class Settings
{
    private const CACHE = 'settings:v1';

    /** key => [type, config key it overrides, allowed values for enums] */
    public static function definitions(): array
    {
        return [
            'registration_open' => ['type' => 'bool', 'config' => 'app.registration_open'],
            'stats_enabled' => ['type' => 'bool', 'config' => 'stats.enabled'],
            'webhooks_enabled' => ['type' => 'bool', 'config' => 'webhooks.enabled'],
            'billing_gateway' => ['type' => 'enum', 'config' => 'billing.gateway', 'options' => app()->isProduction() ? ['none', 'zarinpal'] : ['none', 'zarinpal', 'fake']],
        ];
    }

    /** @var array<string,mixed>|null config defaults as they were before any override */
    private static ?array $defaults = null;

    private function defaults(): array
    {
        if (self::$defaults === null) {
            self::$defaults = [];
            foreach (self::definitions() as $key => $d) {
                self::$defaults[$key] = config($d['config']);
            }
        }

        return self::$defaults;
    }

    /** @return array<string,mixed> stored overrides */
    public function stored(): array
    {
        try {
            return Cache::rememberForever(self::CACHE, fn () => DB::table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all());
        } catch (\Throwable) {
            return [];                         // before the first migration the table does not exist
        }
    }

    /** Push stored values into config. Called on every boot; cheap (cached). */
    public function apply(): void
    {
        $defaults = $this->defaults();
        foreach (self::definitions() as $key => $d) {
            $stored = $this->stored();
            config([$d['config'] => array_key_exists($key, $stored) ? $stored[$key] : $defaults[$key]]);
        }
    }

    /** @return list<array{key:string,type:string,value:mixed,default:mixed,overridden:bool,options?:list<string>}> */
    public function all(): array
    {
        $stored = $this->stored();
        $defaults = $this->defaults();

        return collect(self::definitions())->map(fn ($d, $key) => [
            'key' => $key, 'type' => $d['type'],
            'value' => array_key_exists($key, $stored) ? $stored[$key] : $defaults[$key],
            'default' => $defaults[$key], 'overridden' => array_key_exists($key, $stored),
        ] + (isset($d['options']) ? ['options' => $d['options']] : []))->values()->all();
    }

    /** @return mixed the cleaned value */
    public function clean(string $key, mixed $value): mixed
    {
        $d = self::definitions()[$key] ?? abort(422);

        return match ($d['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? abort(422),
            'enum' => in_array($value, $d['options'], true) ? $value : abort(422),
        };
    }

    public function set(string $key, mixed $value): void
    {
        $value = $this->clean($key, $value);
        DB::table('settings')->upsert([['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]], ['key'], ['value', 'updated_at']);
        $this->refresh();
    }

    public function reset(string $key): void
    {
        abort_unless(isset(self::definitions()[$key]), 422);
        DB::table('settings')->where('key', $key)->delete();
        $this->refresh();
    }

    private function refresh(): void
    {
        Cache::forget(self::CACHE);
        $this->apply();
    }
}
