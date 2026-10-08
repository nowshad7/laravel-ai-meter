<?php

namespace Nsd7\AiMeter\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Models\AiMeterCall;

class PruneCommand extends Command
{
    protected $signature = 'ai-meter:prune {--days= : Override the retention window in days}';

    protected $description = 'Delete AI Meter call records older than the retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('ai-meter.prune.keep_days', 90));

        if ($days <= 0) {
            $this->warn('Retention is disabled (keep_days <= 0); nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($days);
        $deleted = AiMeterCall::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} AI Meter record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
