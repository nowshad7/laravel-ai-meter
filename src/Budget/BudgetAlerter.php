<?php

namespace Nsd7\AiMeter\Budget;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Core\Budget\Budget;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Core\Contracts\SpendStore;
use Nsd7\AiMeter\Events\BudgetThresholdReached;

/**
 * After a call is recorded, checks the budgets that apply to it and dispatches
 * BudgetThresholdReached the first time each threshold is reached within a
 * budget's window. "Already alerted" markers live in the cache and expire with
 * the window, so a new day/month alerts again.
 */
class BudgetAlerter
{
    public function __construct(
        protected SpendStore $spendStore,
        protected Dispatcher $events,
        protected CacheFactory $cache,
    ) {
    }

    /**
     * @param array<int, Budget> $budgets
     */
    public function check(?BudgetScope $scope, array $budgets): void
    {
        $thresholds = $this->thresholds();

        if ($thresholds === []) {
            return;
        }

        foreach ($budgets as $budget) {
            // Per-run ceilings are enforced by MeterRun, not by time windows.
            if ($budget->period === Period::Run || $budget->limitUsd <= 0) {
                continue;
            }

            $target = $budget->scopeType === 'global' ? BudgetScope::global() : $scope;

            if ($target === null || $target->type !== $budget->scopeType) {
                continue;
            }

            $this->checkBudget($target, $budget, $thresholds);
        }
    }

    /**
     * @param array<int, float> $thresholds Ascending.
     */
    protected function checkBudget(BudgetScope $scope, Budget $budget, array $thresholds): void
    {
        $spent = $this->spendStore->spent($scope, $budget->period);
        $fraction = $spent / $budget->limitUsd;

        $now = Carbon::now()->toImmutable();
        $windowStart = $budget->period->windowStart($now);
        $ttl = $this->windowEnd($budget->period, $now);
        $store = $this->cache->store(config('ai-meter.alerts.cache_store'));

        // Mark every reached threshold, but only announce the highest newly
        // reached one (a single jump from 50% to 120% sends one alert, not two).
        $reached = null;

        foreach ($thresholds as $threshold) {
            if ($fraction < $threshold) {
                break;
            }

            $key = implode(':', [
                'ai-meter:alert',
                $scope->key(),
                $budget->period->value,
                $budget->limitUsd,
                $threshold,
                $windowStart?->format('Ymd') ?? 'all',
            ]);

            if ($store->add($key, true, $ttl)) {
                $reached = $threshold;
            }
        }

        if ($reached !== null) {
            $this->events->dispatch(new BudgetThresholdReached($scope, $budget, $reached, $spent));
        }
    }

    /**
     * @return array<int, float>
     */
    protected function thresholds(): array
    {
        $thresholds = array_map('floatval', (array) config('ai-meter.alerts.thresholds', [0.8, 1.0]));
        $thresholds = array_values(array_filter($thresholds, fn (float $t) => $t > 0));
        sort($thresholds);

        return $thresholds;
    }

    /**
     * Cache expiry for the alert marker: the end of the budget window, or
     * forever (null) for all-time budgets.
     */
    protected function windowEnd(Period $period, \DateTimeImmutable $now): ?\DateTimeInterface
    {
        return match ($period) {
            Period::Day => $now->modify('tomorrow'),
            Period::Month => $now->modify('first day of next month')->setTime(0, 0, 0),
            default => null,
        };
    }
}
