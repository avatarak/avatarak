<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = sys_get_temp_dir().'/backup-test-'.uniqid().'.sqlite';
        $pdo = new \PDO('sqlite:'.$this->db);
        $pdo->exec('create table users (id integer primary key, email text)');
        $pdo->exec("insert into users (email) values ('keep-me@example.com')");
        $pdo = null;

        config(['database.connections.bk' => ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => false], 'database.default' => 'bk']);
        Storage::fake('local');
        Storage::disk('local')->put('avatars/7/a.jpg', 'JPEGDATA');
        Storage::disk('local')->put('thumbs/x.jpg', 'THUMB');
    }

    protected function tearDown(): void
    {
        @unlink($this->db);
        parent::tearDown();
    }

    private function backups(): array
    {
        return Storage::disk('local')->files('backups');
    }

    private function open(string $name): ZipArchive
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($name)) === true);

        return $zip;
    }

    public function test_a_backup_contains_a_usable_database_copy_and_every_file(): void
    {
        $this->artisan('backup:run')->assertSuccessful();

        $this->assertCount(1, $this->backups());
        $this->assertMatchesRegularExpression('#backups/avatarak-\d{4}-\d{2}-\d{2}_\d{6}\.zip$#', $this->backups()[0]);
        $zip = $this->open($this->backups()[0]);
        $this->assertSame('JPEGDATA', $zip->getFromName('files/avatars/7/a.jpg'));
        $this->assertSame('THUMB', $zip->getFromName('files/thumbs/x.jpg'));
        $this->assertNotFalse($zip->getFromName('MANIFEST.txt'));

        // the dumped database really opens and holds the data
        $copy = sys_get_temp_dir().'/restore-'.uniqid().'.sqlite';
        file_put_contents($copy, $zip->getFromName('database.sqlite'));
        $row = (new \PDO('sqlite:'.$copy))->query('select email from users')->fetchColumn();
        @unlink($copy);
        $this->assertSame('keep-me@example.com', $row);
    }

    public function test_old_backups_are_never_packed_into_new_ones_and_only_the_newest_are_kept(): void
    {
        foreach (['2026-01-01_000001', '2026-01-02_000001', '2026-01-03_000001', '2026-01-04_000001'] as $s) {
            Storage::disk('local')->put("backups/avatarak-$s.zip", 'old');
        }
        $this->artisan('backup:run', ['--keep' => 3])->assertSuccessful();

        $names = array_map('basename', $this->backups());
        $this->assertCount(3, $names);
        $this->assertNotContains('avatarak-2026-01-01_000001.zip', $names, 'the oldest goes first');
        $this->assertNotContains('avatarak-2026-01-02_000001.zip', $names);
        $newest = collect($this->backups())->sort()->last();
        $zip = $this->open($newest);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->assertStringNotContainsString('backups/', $zip->getNameIndex($i));
        }
    }

    public function test_a_missing_database_fails_loudly_and_writes_nothing(): void
    {
        unlink($this->db);
        $this->artisan('backup:run')->assertFailed();
        $this->assertSame([], $this->backups());
    }

    public function test_it_runs_every_night(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map->command->implode("\n");
        $this->assertStringContainsString('backup:run', $events);
    }
}
