<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class BudgetMiddlewareTest extends TestCase
{
    protected function defineRoutes($router)
    {
        parent::defineRoutes($router);

        Route::middleware(['web', 'auth', 'ai.budget:user'])->get('/protected', fn () => 'ok');
        Route::middleware(['web', 'ai.budget:global'])->get('/protected-global', fn () => 'ok');
    }

    protected function seedCall(string $type, ?string $id, float $cost): void
    {
        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => $cost,
            'scope_type' => $type, 'scope_id' => $id, 'created_at' => now(),
        ]);
    }

    public function test_it_blocks_a_user_over_a_blocking_budget_with_402()
    {
        config(['ai-meter.budgets' => [['scope' => 'user', 'period' => 'month', 'limit' => 50, 'action' => 'block']]]);

        $user = $this->makeUser();
        $this->seedCall('user', (string) $user->id, 60);

        $this->actingAs($user)->get('/protected')->assertStatus(402);
    }

    public function test_it_allows_a_user_under_budget()
    {
        config(['ai-meter.budgets' => [['scope' => 'user', 'period' => 'month', 'limit' => 50, 'action' => 'block']]]);

        $user = $this->makeUser();
        $this->seedCall('user', (string) $user->id, 10);

        $this->actingAs($user)->get('/protected')->assertOk()->assertSee('ok');
    }

    public function test_an_alert_budget_never_blocks()
    {
        config(['ai-meter.budgets' => [['scope' => 'user', 'period' => 'month', 'limit' => 50, 'action' => 'alert']]]);

        $user = $this->makeUser();
        $this->seedCall('user', (string) $user->id, 999);

        $this->actingAs($user)->get('/protected')->assertOk();
    }

    public function test_global_budget_blocks_everyone()
    {
        config(['ai-meter.budgets' => [['scope' => 'global', 'period' => 'day', 'limit' => 100, 'action' => 'block']]]);

        $this->seedCall('user', '1', 120);

        $this->get('/protected-global')->assertStatus(402);
    }

    public function test_no_budget_means_no_block()
    {
        config(['ai-meter.budgets' => []]);

        $user = $this->makeUser();
        $this->actingAs($user)->get('/protected')->assertOk();
    }
}
