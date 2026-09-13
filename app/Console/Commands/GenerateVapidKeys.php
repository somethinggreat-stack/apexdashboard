<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID key pair for Web Push and print the .env lines to add';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('VAPID keys generated. Add these to your server .env (keep the private key secret):');
        $this->newLine();
        $this->line('VAPID_SUBJECT=mailto:admin@apexgrowthsolution.com');
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->newLine();
        $this->comment('Then run: php artisan config:cache');

        return self::SUCCESS;
    }
}
