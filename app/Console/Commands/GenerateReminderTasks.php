<?php

namespace App\Console\Commands;

use App\Services\ReminderTaskGenerator;
use Illuminate\Console\Command;

class GenerateReminderTasks extends Command
{
    protected $signature = 'reminders:generate';

    protected $description = 'Generate idempotent in-dashboard reminder tasks from active rules';

    public function handle(ReminderTaskGenerator $generator): int
    {
        $count = $generator->generate();
        $this->info("Generated {$count} reminder task(s).");

        return self::SUCCESS;
    }
}
