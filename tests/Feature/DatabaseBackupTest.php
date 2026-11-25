<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_backup_command_writes_a_verified_sqlite_copy_to_the_configured_disk(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'dashboard-source-');
        $source = new PDO('sqlite:'.$sourcePath);
        $source->exec('CREATE TABLE backup_probe (value TEXT NOT NULL)');
        $source->exec("INSERT INTO backup_probe VALUES ('restorable-data')");
        $source = null;
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $sourcePath, 'vaccination.backup_disk' => 'local']);
        DB::purge('sqlite');
        Storage::fake('local');

        try {
            $this->artisan('db:backup')->assertSuccessful()->expectsOutputToContain('SQLite backup verified and saved');

            $files = Storage::disk('local')->allFiles('backups');
            $this->assertCount(1, $files);
            $backup = new PDO('sqlite:'.Storage::disk('local')->path($files[0]));
            $this->assertSame('ok', $backup->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertSame('restorable-data', $backup->query('SELECT value FROM backup_probe')->fetchColumn());
        } finally {
            DB::purge('sqlite');
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }
    }
}
