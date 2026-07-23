<?php

namespace App\Console\Commands;

use App\Support\RecycleBin;
use Illuminate\Console\Command;

class PurgeRecycleBin extends Command
{
    protected $signature = 'recyclebin:purge';

    protected $description = 'Permanently remove business owners and clients that have been in the Recycle Bin longer than the retention window (' . RecycleBin::RETENTION_DAYS . ' days).';

    public function handle(): int
    {
        $result = RecycleBin::purgeExpired();

        $this->info("Recycle Bin purged: {$result['owners']} business owner(s), {$result['clients']} client(s) removed for good.");

        return self::SUCCESS;
    }
}
