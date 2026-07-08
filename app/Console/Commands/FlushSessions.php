<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FlushSessions extends Command
{
    protected $signature = 'sessions:flush';

    protected $description = 'Log everyone out — clears all active sessions (used on deploy so the team logs back in).';

    public function handle(): int
    {
        $driver = config('session.driver');

        if ($driver === 'database') {
            $table = config('session.table', 'sessions');
            $count = DB::table($table)->count();
            DB::table($table)->delete();
            $this->info("Flushed {$count} session(s) from '{$table}' — everyone must log in again.");
        } elseif ($driver === 'file') {
            $dir = config('session.files');
            $n = 0;
            foreach ((array) glob($dir . '/*') as $f) {
                if (is_file($f) && basename($f) !== '.gitignore') {
                    @unlink($f);
                    $n++;
                }
            }
            $this->info("Flushed {$n} session file(s).");
        } else {
            $this->warn("Session driver '{$driver}' not handled — skipped.");
        }

        return self::SUCCESS;
    }
}
