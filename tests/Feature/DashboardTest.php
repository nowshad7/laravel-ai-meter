<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Models\AiMeterRun;
use Nsd7\AiMeter\Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function seedCall(): AiMeterCall
    {
        return AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 1.25,
            'prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150,
            'scope_type' => 'user', 'scope_id' => '1', 'status' => 'success', 'created_at' => now(),
        ]);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('ai-meter.overview'))->assertRedirect('/login');
    }

    public function test_overview_renders()
    {
        $this->seedCall();

        $this->actingAs($this->makeUser())
            ->get(route('ai-meter.overview'))
            ->assertOk()
            ->assertViewIs('ai-meter::overview')
            ->assertSee('gpt-4o');
    }

    public function test_calls_list_renders_and_filters()
    {
        $this->seedCall();
        AiMeterCall::create(['provider' => 'anthropic', 'model' => 'claude-3-5-haiku', 'cost_usd' => 0.1, 'created_at' => now()]);

        $response = $this->actingAs($this->makeUser())
            ->get(route('ai-meter.calls', ['provider' => 'openai']))
            ->assertOk();

        $calls = $response->viewData('calls');
        $this->assertSame(1, $calls->total());
        $this->assertSame('openai', $calls->first()->provider);
    }

    public function test_call_detail_renders()
    {
        $call = $this->seedCall();

        $this->actingAs($this->makeUser())
            ->get(route('ai-meter.calls.show', $call->id))
            ->assertOk()
            ->assertSee('gpt-4o');
    }

    public function test_runs_list_and_detail_render()
    {
        $run = AiMeterRun::create([
            'run_id' => 'run-abc', 'label' => 'nightly agent', 'status' => 'completed',
            'scope_type' => 'user', 'scope_id' => '1', 'step_count' => 2, 'tool_call_count' => 1,
            'cost_usd' => 3.5, 'started_at' => now(),
        ]);
        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 3.5, 'total_tokens' => 10,
            'run_id' => 'run-abc', 'created_at' => now(),
        ]);

        $this->actingAs($this->makeUser())
            ->get(route('ai-meter.runs'))
            ->assertOk()
            ->assertViewIs('ai-meter::runs')
            ->assertSee('nightly agent');

        $this->actingAs($this->makeUser())
            ->get(route('ai-meter.runs.show', $run->id))
            ->assertOk()
            ->assertSee('gpt-4o');
    }

    public function test_runs_can_be_disabled()
    {
        config(['ai-meter.features.runs' => false]);

        $this->actingAs($this->makeUser())
            ->get(route('ai-meter.runs'))
            ->assertNotFound();
    }

    public function test_the_gate_can_deny_access()
    {
        Gate::define('viewAiMeter', fn ($user) => $user->email === 'admin@example.com');

        $this->actingAs($this->makeUser(['email' => 'nobody@example.com']))
            ->get(route('ai-meter.overview'))
            ->assertForbidden();
    }
}
