<?php

namespace Nsd7\AiMeter\Tests\Unit;

use DateTimeImmutable;
use Nsd7\AiMeter\Core\Budget\Budget;
use Nsd7\AiMeter\Core\Budget\BudgetGuard;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Core\Contracts\SpendStore;
use PHPUnit\Framework\TestCase;

class BudgetTest extends TestCase
{
    public function test_period_window_starts()
    {
        $now = new DateTimeImmutable('2024-06-15 13:45:00');

        $this->assertSame('2024-06-15 00:00:00', Period::Day->windowStart($now)->format('Y-m-d H:i:s'));
        $this->assertSame('2024-06-01 00:00:00', Period::Month->windowStart($now)->format('Y-m-d H:i:s'));
        $this->assertNull(Period::Total->windowStart($now));
        $this->assertNull(Period::Run->windowStart($now));
    }

    public function test_guard_allows_when_no_budgets()
    {
        $guard = new BudgetGuard($this->spendStore(0), []);

        $this->assertTrue($guard->evaluate(BudgetScope::user(1))->allowed);
    }

    public function test_guard_blocks_when_over_a_blocking_budget()
    {
        $guard = new BudgetGuard($this->spendStore(60), [
            new Budget('user', Period::Month, 50, Budget::ACTION_BLOCK),
        ]);

        $decision = $guard->evaluate(BudgetScope::user(1));

        $this->assertFalse($decision->allowed);
        $this->assertTrue($decision->exceeded);
        $this->assertSame(0.0, $decision->remainingUsd());
    }

    public function test_guard_allows_but_flags_when_over_an_alert_budget()
    {
        $guard = new BudgetGuard($this->spendStore(60), [
            new Budget('user', Period::Month, 50, Budget::ACTION_ALERT),
        ]);

        $decision = $guard->evaluate(BudgetScope::user(1));

        $this->assertTrue($decision->allowed);
        $this->assertTrue($decision->exceeded);
    }

    public function test_additional_cost_can_push_a_scope_over()
    {
        $guard = new BudgetGuard($this->spendStore(49), [
            new Budget('user', Period::Month, 50, Budget::ACTION_BLOCK),
        ]);

        $this->assertTrue($guard->evaluate(BudgetScope::user(1))->allowed);
        $this->assertFalse($guard->evaluate(BudgetScope::user(1), additionalUsd: 2)->allowed);
    }

    protected function spendStore(float $amount): SpendStore
    {
        return new class($amount) implements SpendStore {
            public function __construct(private float $amount)
            {
            }

            public function spent(BudgetScope $scope, Period $period): float
            {
                return $this->amount;
            }
        };
    }
}
