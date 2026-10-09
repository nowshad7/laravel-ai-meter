<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Budget\CacheSpendStore;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Core\Contracts\SpendStore;
use Nsd7\AiMeter\Facades\Meter;
use Nsd7\AiMeter\Tests\TestCase;

class CacheSpendStoreTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('ai-meter.spend_store.driver', 'cache');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function log(float $usd, int|string|null $user = 1): void
    {
        $attributes = ['provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => $usd];

        if ($user !== null) {
            $attributes['scope'] = BudgetScope::user($user);
        }

        Meter::log($attributes);
    }

    public function test_the_cache_driver_is_bound()
    {
        $this->assertInstanceOf(CacheSpendStore::class, $this->app->make(SpendStore::class));
    }

    public function test_it_counts_scoped_spend_per_period()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');

        $this->log(2.5);
        $this->log(1.25);

        $this->assertEqualsWithDelta(3.75, Meter::spent(BudgetScope::user(1), Period::Day), 1e-9);
        $this->assertEqualsWithDelta(3.75, Meter::spent(BudgetScope::user(1), Period::Month), 1e-9);
        $this->assertEqualsWithDelta(3.75, Meter::spent(BudgetScope::user(1), Period::Total), 1e-9);
        $this->assertSame(0.0, Meter::spent(BudgetScope::user(2), Period::Day));
    }

    public function test_global_aggregates_every_scope_including_unscoped()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');

        $this->log(5, user: 1);
        $this->log(3, user: 2);
        $this->log(2, user: null); // unscoped — still global

        $this->assertEqualsWithDelta(10.0, Meter::spent(BudgetScope::global(), Period::Day), 1e-9);
        $this->assertEqualsWithDelta(5.0, Meter::spent(BudgetScope::user(1), Period::Day), 1e-9);
    }

    public function test_day_counter_resets_next_day_but_month_persists()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');
        $this->log(4);

        Carbon::setTestNow('2024-06-16 12:00:00');
        $this->log(6);

        $this->assertEqualsWithDelta(6.0, Meter::spent(BudgetScope::user(1), Period::Day), 1e-9);   // only the 16th
        $this->assertEqualsWithDelta(10.0, Meter::spent(BudgetScope::user(1), Period::Month), 1e-9); // both
    }

    public function test_month_counter_resets_next_month()
    {
        Carbon::setTestNow('2024-06-30 12:00:00');
        $this->log(7);

        Carbon::setTestNow('2024-07-01 12:00:00');
        $this->assertSame(0.0, Meter::spent(BudgetScope::user(1), Period::Month));
        $this->assertEqualsWithDelta(7.0, Meter::spent(BudgetScope::user(1), Period::Total), 1e-9);
    }

    public function test_eight_decimal_precision_is_preserved()
    {
        $this->log(0.00000123);
        $this->log(0.00000077);

        $this->assertEqualsWithDelta(0.000002, Meter::spent(BudgetScope::user(1), Period::Total), 1e-12);
    }

    public function test_it_enforces_a_blocking_budget_through_the_counter()
    {
        config()->set('ai-meter.budgets', [
            ['scope' => 'user', 'period' => 'month', 'limit' => 10, 'action' => 'block'],
        ]);

        $this->log(6);
        $this->assertTrue(Meter::check(BudgetScope::user(1))->allowed);

        $this->log(5); // now 11 >= 10
        $decision = Meter::check(BudgetScope::user(1));
        $this->assertFalse($decision->allowed);
        $this->assertTrue($decision->exceeded);
    }

    public function test_remaining_reflects_the_counter()
    {
        config()->set('ai-meter.budgets', [
            ['scope' => 'user', 'period' => 'month', 'limit' => 20, 'action' => 'block'],
        ]);

        $this->log(8);

        $this->assertEqualsWithDelta(12.0, Meter::remaining(BudgetScope::user(1)), 1e-9);
    }
}
