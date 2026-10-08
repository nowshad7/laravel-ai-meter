<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Budget\DatabaseSpendStore;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class SpendStoreTest extends TestCase
{
    protected function seedCall(string $scopeType, ?string $scopeId, float $cost, Carbon $at): void
    {
        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => $cost,
            'scope_type' => $scopeType, 'scope_id' => $scopeId, 'created_at' => $at,
        ]);
    }

    public function test_it_sums_scoped_spend_within_a_period()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');
        $store = new DatabaseSpendStore();

        $this->seedCall('user', '1', 10, Carbon::parse('2024-06-15 09:00:00')); // today
        $this->seedCall('user', '1', 5, Carbon::parse('2024-06-02 09:00:00'));  // this month, not today
        $this->seedCall('user', '1', 99, Carbon::parse('2024-05-20 09:00:00')); // last month
        $this->seedCall('user', '2', 50, Carbon::parse('2024-06-15 09:00:00')); // other user

        $this->assertSame(10.0, $store->spent(BudgetScope::user(1), Period::Day));
        $this->assertSame(15.0, $store->spent(BudgetScope::user(1), Period::Month));
        $this->assertSame(114.0, $store->spent(BudgetScope::user(1), Period::Total));
        $this->assertSame(50.0, $store->spent(BudgetScope::user(2), Period::Month));

        Carbon::setTestNow();
    }

    public function test_global_scope_sums_everyone()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');
        $store = new DatabaseSpendStore();

        $this->seedCall('user', '1', 10, Carbon::now());
        $this->seedCall('user', '2', 20, Carbon::now());

        $this->assertSame(30.0, $store->spent(BudgetScope::global(), Period::Month));

        Carbon::setTestNow();
    }
}
