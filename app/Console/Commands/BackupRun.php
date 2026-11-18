<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--keep= : How many backups to keep (default BACKUP_KEEP)}';

    protected $description = 'Back up the database and every uploaded file into one dated zip';

    public function handle(): int
    {
        $stamp = now()->format('Y-m-d_His');
        $tmp = sys_get_temp_dir().'/avatarak-backup-'.$stamp.'-'.bin2hex(random_bytes(3));
        mkdir($tmp, 0700, true);

        try {
            $dump = $this->dumpDatabase($tmp);
            $zipPath = $tmp.'/avatarak-'.$stamp.'.zip';
            $files = $this->zip($zipPath, $dump);

            $disk = Storage::disk(config('backup.disk'));
            $name = trim(config('backup.path'), '/').'/avatarak-'.$stamp.'.zip';
            $disk->put($name, fopen($zipPath, 'r'));

            $this->info(sprintf('Backup written: %s (%s, %d files)', $name, $this->size((int) filesize($zipPath)), $files));
            $this->prune($disk);
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            foreach (glob($tmp.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }

        return self::SUCCESS;
    }

    /** @return string path of the dump file */
    private function dumpDatabase(string $dir): string
    {
        $conn = config('database.default');
        $cfg = config("database.connections.$conn");

        if ($cfg['driver'] === 'sqlite') {
            $src = $cfg['database'];
            if ($src === ':memory:' || ! is_file($src)) {
                throw new \RuntimeException('The SQLite database file was not found.');
            }
            $out = $dir.'/database.sqlite';
            // VACUUM INTO makes a consistent copy even while the app is writing
            $pdo = DB::connection($conn)->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($out));

            return $out;
        }

        if ($cfg['driver'] === 'mysql' || $cfg['driver'] === 'mariadb') {
            $out = $dir.'/database.sql';
            $process = new Process(array_filter([
                'mysqldump', '--single-transaction', '--quick', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
                '-h', $cfg['host'], '-P', (string) $cfg['port'], '-u', $cfg['username'], '--result-file='.$out, $cfg['database'],
            ]), null, ['MYSQL_PWD' => (string) $cfg['password']], null, 600);   // the password never appears on a command line
            $process->run();
            if (! $process->isSuccessful() || ! is_file($out)) {
                throw new \RuntimeException('mysqldump failed: '.trim($process->getErrorOutput()) ?: 'is mysqldump installed in this container?');
            }

            return $out;
        }

        throw new \RuntimeException("Backups of the {$cfg['driver']} driver are not supported.");
    }

    private function zip(string $zipPath, string $dump): int
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the zip file.');
        }
        $zip->addFile($dump, basename($dump));
        $count = 1;

        $disk = Storage::disk(config('avatar.disk'));
        $skip = trim(config('backup.path'), '/').'/';
        foreach ($disk->allFiles() as $path) {
            if (str_starts_with($path, $skip) || str_starts_with($path, 'private/') && false) {
                continue;                                  // never put old backups inside a new backup
            }
            $zip->addFromString('files/'.$path, (string) $disk->get($path));
            $count++;
        }
        $zip->addFromString('MANIFEST.txt', "Avatarak.ir backup\ncreated: ".now()->toIso8601String()."\ndatabase: ".basename($dump)."\nfiles: ".($count - 1)."\nrestore: see /docs/deployment\n");
        $zip->close();

        return $count - 1;
    }

    private function prune($disk): void
    {
        $keep = max(1, (int) ($this->option('keep') ?: config('backup.keep')));
        $all = collect($disk->files(trim(config('backup.path'), '/')))->filter(fn ($f) => preg_match('#avatarak-\d{4}-\d{2}-\d{2}_\d{6}\.zip$#', $f))->sort()->values();
        foreach ($all->slice(0, max(0, $all->count() - $keep)) as $old) {
            $disk->delete($old);
            $this->line("Deleted old backup $old");
        }
    }

    private function size(int $bytes): string
    {
        return $bytes > 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024, 1).' KB';
    }
}
