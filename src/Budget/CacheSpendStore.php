<?php

namespace Nsd7\AiMeter\Budget;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Core\Contracts\RecordsSpend;
use Nsd7\AiMeter\Core\Contracts\SpendStore;

/**
 * A SpendStore backed by cache counters (e.g. Redis) rather than per-request
 * SUM aggregation of the calls table. Each recorded cost increments a counter
 * per scope and per period window; reads are O(1) GETs. Ideal for high-volume
 * apps enforcing budgets on every request.
 *
 * Counters reflect spend recorded *after* this store became active (they are
 * not backfilled from existing rows); use DatabaseSpendStore when exact
 * historical aggregation matters. Amounts are stored as integer "units" of
 * 1e-8 USD to match the 8-decimal cost column and keep counters integral.
 */
class CacheSpendStore implements SpendStore, RecordsSpend
{
    /** USD → integer units (8 decimal places), matching decimal(14, 8). */
    protected const SCALE = 100_000_000;

    public function __construct(protected CacheFactory $cache)
    {
    }

    public function spent(BudgetScope $scope, Period $period): float
    {
        $scopeKey = $scope->type === 'global' ? 'global' : $scope->key();
        $key = $this->key($scopeKey, $this->window($period, Carbon::now()->toImmutable()));

        return ((int) $this->store()->get($key, 0)) / self::SCALE;
    }

    public function addSpend(?BudgetScope $scope, float $usd): void
    {
        $units = (int) round($usd * self::SCALE);

        if ($units === 0) {
            return;
        }

        $at = Carbon::now()->toImmutable();

        // Every call counts toward the global bucket …
        $this->bump('global', $units, $at);

        // … and toward its own scope bucket, when it has a non-global one.
        if ($scope !== null && $scope->type !== 'global') {
            $this->bump($scope->key(), $units, $at);
        }
    }

    protected function bump(string $scopeKey, int $units, DateTimeImmutable $at): void
    {
        $store = $this->store();

        foreach ([Period::Day, Period::Month, Period::Total] as $period) {
            $key = $this->key($scopeKey, $this->window($period, $at));
            $ttl = $this->ttl($period, $at);

            // Ensure the key exists with the right expiry, then add to it.
            $store->add($key, 0, $ttl);
            $store->increment($key, $units);
        }
    }

    protected function window(Period $period, DateTimeImmutable $at): string
    {
        return match ($period) {
            Period::Day => 'day:' . $at->format('Ymd'),
            Period::Month => 'month:' . $at->format('Ym'),
            Period::Run, Period::Total => 'total',
        };
    }

    protected function ttl(Period $period, DateTimeImmutable $at): ?DateTimeInterface
    {
        return match ($period) {
            Period::Day => $at->modify('tomorrow'),
            Period::Month => $at->modify('first day of next month')->setTime(0, 0, 0),
            default => null,
        };
    }

    protected function key(string $scopeKey, string $window): string
    {
        $prefix = (string) config('ai-meter.spend_store.prefix', 'ai-meter:spend');

        return "{$prefix}:{$scopeKey}:{$window}";
    }

    protected function store(): Repository
    {
        return $this->cache->store(config('ai-meter.spend_store.cache_store'));
    }
}
