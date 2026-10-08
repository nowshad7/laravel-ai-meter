<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class ApiBudgetTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        // The API route is registered at boot, so enable it before booting.
        $app['config']->set('ai-meter.features.api', true);
    }

    protected function seedSpend(string $type, ?string $id, float $cost): void
    {
        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => $cost,
            'scope_type' => $type, 'scope_id' => $id, 'created_at' => now(),
        ]);
    }

    public function test_it_reports_budget_for_the_authenticated_user()
    {
        config(['ai-meter.budgets' => [['scope' => 'user', 'period' => 'month', 'limit' => 50, 'action' => 'block']]]);

        $user = $this->makeUser();
        $this->seedSpend('user', (string) $user->id, 10);

        $this->actingAs($user)
            ->getJson(route('ai-meter.api.budget'))
            ->assertOk()
            ->assertJsonPath('scope.type', 'user')
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('spend.month', 10)
            ->assertJsonPath('budgets.0.period', 'month')
            ->assertJsonPath('budgets.0.limit_usd', 50)
            ->assertJsonPath('budgets.0.spent_usd', 10)
            ->assertJsonPath('budgets.0.remaining_usd', 40)
            ->assertJsonPath('budgets.0.exceeded', false);
    }

    public function test_it_reports_not_allowed_when_over_a_blocking_budget()
    {
        config(['ai-meter.budgets' => [['scope' => 'user', 'period' => 'month', 'limit' => 50, 'action' => 'block']]]);

        $user = $this->makeUser();
        $this->seedSpend('user', (string) $user->id, 60);

        $this->actingAs($user)
            ->getJson(route('ai-meter.api.budget'))
            ->assertOk()
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('budgets.0.exceeded', true)
            ->assertJsonPath('budgets.0.remaining_usd', 0);
    }

    public function test_it_honours_an_explicit_scope_query()
    {
        $this->seedSpend('user', '1', 30);

        $this->actingAs($this->makeUser())
            ->getJson(route('ai-meter.api.budget', ['scope_type' => 'global']))
            ->assertOk()
            ->assertJsonPath('scope.type', 'global')
            ->assertJsonPath('spend.total', 30);
    }

    public function test_no_budgets_means_allowed_with_empty_list()
    {
        config(['ai-meter.budgets' => []]);

        $this->actingAs($this->makeUser())
            ->getJson(route('ai-meter.api.budget'))
            ->assertOk()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('budgets', []);
    }
}
