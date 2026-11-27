<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

#[Signature('app:initialize-demo-database')]
#[Description('Run production migrations and seed demo data once when needed')]
class InitializeDemoDatabase extends Command
{
    public function handle(): int
    {
        $runtimeDatabaseUrl = config('database.connections.pgsql.url');
        $migrationDatabaseUrl = config('database.connections.pgsql.migration_url');

        if (is_string($migrationDatabaseUrl) && $migrationDatabaseUrl !== '') {
            config(['database.connections.pgsql.url' => $migrationDatabaseUrl]);
            DB::purge('pgsql');
        }

        try {
            $migrationExitCode = Artisan::call('migrate', ['--force' => true]);
            $this->output->write(Artisan::output());
        } finally {
            if (is_string($migrationDatabaseUrl) && $migrationDatabaseUrl !== '') {
                DB::purge('pgsql');
                config(['database.connections.pgsql.url' => $runtimeDatabaseUrl]);
            }
        }

        if ($migrationExitCode !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (! config('vaccination.demo_mode')) {
            return self::SUCCESS;
        }

        $administratorEmail = config('vaccination.demo_accounts.0.email');

        if (User::query()->where('email', $administratorEmail)->exists()) {
            return self::SUCCESS;
        }

        $seederExitCode = Artisan::call('db:seed', ['--force' => true]);
        $this->output->write(Artisan::output());

        return $seederExitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
