<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:install {--force : Regenerate even if keys already exist}';

    protected $description = 'Ensure VAPID keys exist in .env for Web Push (auto-runs on deploy; no terminal needed)';

    public function handle(): int
    {
        $path = base_path('.env');

        if (! is_file($path) || ! is_writable($path)) {
            $this->warn('webpush:install: .env not found or not writable — skipping.');
            return self::SUCCESS;   // never fail a deploy over this
        }

        $env = file_get_contents($path);

        // Already provisioned (a non-empty public key present) and not forcing? Done.
        if (! $this->option('force') && preg_match('/^VAPID_PUBLIC_KEY=\S+/m', $env)) {
            $this->info('webpush:install: VAPID keys already present.');
            return self::SUCCESS;
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->warn('webpush:install: could not generate keys (' . $e->getMessage() . ') — skipping.');
            return self::SUCCESS;
        }

        // Strip any existing (possibly empty) VAPID_* lines, then append a fresh block.
        $env = preg_replace('/^VAPID_(SUBJECT|PUBLIC_KEY|PRIVATE_KEY)=.*$\n?/m', '', $env);
        $subject = env('VAPID_SUBJECT') ?: 'mailto:admin@apexgrowthsolution.com';
        $block = rtrim($env, "\n") . "\n\n"
            . "VAPID_SUBJECT={$subject}\n"
            . "VAPID_PUBLIC_KEY={$keys['publicKey']}\n"
            . "VAPID_PRIVATE_KEY={$keys['privateKey']}\n";

        file_put_contents($path, $block);
        $this->info('webpush:install: generated and saved VAPID keys to .env.');

        return self::SUCCESS;
    }
}
