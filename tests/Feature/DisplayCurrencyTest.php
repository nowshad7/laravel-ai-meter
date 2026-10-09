<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class DisplayCurrencyTest extends TestCase
{
    protected function log(): void
    {
        app(Meter::class)->log([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'cost_usd' => 2.0,
            'scope' => BudgetScope::user(1),
        ]);
    }

    public function test_cost_display_is_null_without_an_fx_rate()
    {
        $this->log();

        $this->assertNull(AiMeterCall::first()->cost_display);
    }

    public function test_a_numeric_fx_rate_converts_the_display_cost()
    {
        config()->set('ai-meter.pricing.fx_rate', 0.8);

        $this->log();

        // cost 2.0 USD * 0.8 = 1.6
        $this->assertEqualsWithDelta(1.6, AiMeterCall::first()->cost_display, 1e-9);
    }

    public function test_a_callable_fx_rate_returns_the_rate_to_apply()
    {
        // The callable receives the USD amount and returns the rate to multiply by.
        config()->set('ai-meter.pricing.fx_rate', fn ($usd) => 1.25);

        $this->log();

        // cost 2.0 USD * rate 1.25 = 2.5
        $this->assertEqualsWithDelta(2.5, AiMeterCall::first()->cost_display, 1e-9);
    }
}
