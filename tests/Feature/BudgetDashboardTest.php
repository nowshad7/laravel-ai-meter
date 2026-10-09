<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class BudgetDashboardTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function seedCall(string $scopeType, ?string $scopeId, float $cost): void
    {
        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => $cost,
            'scope_type' => $scopeType, 'scope_id' => $scopeId, 'created_at' => now(),
        ]);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('ai-meter.budgets'))->assertRedirect('/login');
    }

    public function test_the_budgets_tab_is_linked_from_the_dashboard()
    {
        $this->actingAs($this->makeUser())
            ->get(route('ai-meter.overview'))
            ->assertOk()
            ->assertSee(route('ai-meter.budgets'));
    }

    public function test_empty_state_when_no_budgets_are_configured()
    {
        config()->set('ai-meter.budgets', []);

        $response = $this->actingAs($this->makeUser())->get(route('ai-meter.budgets'))->assertOk();

        $response->assertViewHas('budgets', []);
        $response->assertSee('config/ai-meter.php');
    }

    public function test_a_global_budget_shows_its_live_used_fraction()
    {
        config()->set('ai-meter.budgets', [
            ['scope' => 'global', 'period' => 'day', 'limit' => 100, 'action' => 'alert'],
        ]);
        $this->seedCall('user', '1', 40);
        $this->seedCall('user', '2', 35);

        $response = $this->actingAs($this->makeUser())->get(route('ai-meter.budgets'))->assertOk();

        $budgets = $response->viewData('budgets');
        $this->assertCount(1, $budgets);
        $this->assertEqualsWithDelta(75.0, $budgets[0]['global']['spent'], 1e-9);
        $this->assertEqualsWithDelta(0.75, $budgets[0]['global']['used'], 1e-9);
        $response->assertSee('75% ');
    }

    public function test_a_user_budget_lists_top_spenders_against_the_limit()
    {
        config()->set('ai-meter.budgets', [
            ['scope' => 'user', 'period' => 'month', 'limit' => 20, 'action' => 'block'],
        ]);
        $this->seedCall('user', '1', 18);
        $this->seedCall('user', '2', 5);
        $this->seedCall('tenant', '9', 99); // different type — ignored

        $response = $this->actingAs($this->makeUser())->get(route('ai-meter.budgets'))->assertOk();

        $scopes = $response->viewData('budgets')[0]['scopes'];
        $this->assertSame('1', $scopes[0]['id']);
        $this->assertEqualsWithDelta(18.0, $scopes[0]['spent'], 1e-9);
        $this->assertEqualsWithDelta(0.9, $scopes[0]['used'], 1e-9);
        $this->assertSame('2', $scopes[1]['id']);
        $this->assertCount(2, $scopes);
    }

    public function test_top_spenders_are_scoped_to_the_budget_period()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');
        config()->set('ai-meter.budgets', [
            ['scope' => 'user', 'period' => 'day', 'limit' => 10, 'action' => 'alert'],
        ]);

        AiMeterCall::create(['provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 4,
            'scope_type' => 'user', 'scope_id' => '1', 'created_at' => Carbon::parse('2024-06-15 09:00:00')]);
        AiMeterCall::create(['provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 99,
            'scope_type' => 'user', 'scope_id' => '1', 'created_at' => Carbon::parse('2024-06-14 09:00:00')]);

        $response = $this->actingAs($this->makeUser())->get(route('ai-meter.budgets'))->assertOk();

        $this->assertEqualsWithDelta(4.0, $response->viewData('budgets')[0]['scopes'][0]['spent'], 1e-9);
    }

    public function test_a_run_budget_is_shown_without_a_time_window()
    {
        config()->set('ai-meter.budgets', [
            ['scope' => 'user', 'period' => 'run', 'limit' => 2, 'action' => 'block'],
        ]);

        $response = $this->actingAs($this->makeUser())->get(route('ai-meter.budgets'))->assertOk();

        $budget = $response->viewData('budgets')[0];
        $this->assertSame([], $budget['scopes']);
        $this->assertNull($budget['global']);
        $response->assertSee(__('ai-meter::messages.per_run_note'));
    }

    public function test_alert_thresholds_are_passed_to_the_view()
    {
        config()->set('ai-meter.budgets', [
            ['scope' => 'global', 'period' => 'day', 'limit' => 10, 'action' => 'alert'],
        ]);
        config()->set('ai-meter.alerts.thresholds', [0.5, 0.9]);

        $this->actingAs($this->makeUser())
            ->get(route('ai-meter.budgets'))
            ->assertOk()
            ->assertViewHas('thresholds', [0.5, 0.9])
            ->assertSee('Alerts at');
    }
}
