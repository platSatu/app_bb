<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Backup database harian ke storage/app/private/backups (14 terbaru disimpan)';

    public function handle(DatabaseBackup $backup): int
    {
        $this->info('Backup dibuat: '.$backup->create());

        return self::SUCCESS;
    }
}
