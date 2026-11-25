<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PDO;
use Throwable;

class BackupSqliteDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Create and verify a timestamped SQLite database backup';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->error('This backup command supports SQLite databases only.');

            return self::FAILURE;
        }

        $source = DB::connection('sqlite')->getDatabaseName();
        if ($source === ':memory:' || ! is_file($source)) {
            $this->error('A file-based SQLite database is required for backups.');

            return self::FAILURE;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'immunicare-backup-');
        if ($temporaryPath === false) {
            $this->error('Unable to prepare a temporary backup file.');

            return self::FAILURE;
        }

        try {
            $pdo = DB::connection('sqlite')->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($temporaryPath));

            $verification = new PDO('sqlite:'.$temporaryPath);
            $integrity = $verification->query('PRAGMA integrity_check')->fetchColumn();
            $verification = null;

            if ($integrity !== 'ok' || filesize($temporaryPath) === 0) {
                throw new \RuntimeException('SQLite integrity verification failed.');
            }

            $filename = 'vaccination-dashboard-'.now()->format('Ymd-His').'-'.str()->random(6).'.sqlite';
            $relativePath = 'backups/'.$filename;
            $stream = fopen($temporaryPath, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to read the verified backup.');
            }

            try {
                $saved = Storage::disk(config('vaccination.backup_disk'))->put($relativePath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $saved) {
                throw new \RuntimeException('The configured backup disk rejected the file.');
            }

            $disk = Storage::disk(config('vaccination.backup_disk'));
            $retentionDays = max(1, config('vaccination.backup_retention_days'));
            $cutoff = now()->subDays($retentionDays)->timestamp;
            foreach ($disk->files('backups') as $oldBackup) {
                if ($oldBackup !== $relativePath && preg_match('/^backups\/vaccination-dashboard-\d{8}-\d{6}-[A-Za-z0-9]{6}\.sqlite$/', $oldBackup) && $disk->lastModified($oldBackup) < $cutoff) {
                    $disk->delete($oldBackup);
                }
            }

            $this->info("SQLite backup verified and saved: {$relativePath}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('SQLite backup failed.', ['exception' => $exception]);
            $this->error('Backup failed. Review the application log; the previous backups were retained.');

            return self::FAILURE;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
