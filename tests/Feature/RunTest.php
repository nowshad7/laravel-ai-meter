<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Budget\Exceptions\BudgetExceededException;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Models\AiMeterRun;
use Nsd7\AiMeter\Runs\Exceptions\RunLimitExceededException;
use Nsd7\AiMeter\Tests\TestCase;

class RunTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('ai-meter.pricing.prices', [
            'openai' => ['gpt-4o' => ['input' => 2.5, 'output' => 10]],
        ]);
    }

    protected function meter(): Meter
    {
        return app(Meter::class);
    }

    public function test_a_run_groups_calls_and_tracks_aggregates()
    {
        $run = $this->meter()->run(BudgetScope::user(1), ['label' => 'test run']);

        $run->record([
            'provider' => 'openai', 'model' => 'gpt-4o',
            'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 0],
        ]);
        $run->record([
            'provider' => 'openai', 'model' => 'gpt-4o', 'tool_name' => 'search',
            'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 0],
        ]);
        $run->finish();

        $this->assertSame(2, AiMeterCall::where('run_id', $run->id())->count());

        $row = AiMeterRun::first();
        $this->assertSame('completed', $row->status);
        $this->assertSame(2, $row->step_count);
        $this->assertSame(1, $row->tool_call_count);
        $this->assertSame(5.0, $row->cost_usd); // 2.5 + 2.5
        $this->assertNotNull($row->ended_at);
    }

    public function test_guard_throws_on_the_tool_call_cap()
    {
        $run = $this->meter()->run(BudgetScope::user(1), ['max_tool_calls' => 2]);

        foreach (range(1, 2) as $i) {
            $run->record([
                'provider' => 'openai', 'model' => 'gpt-4o', 'tool_name' => 'search',
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ]);
        }

        try {
            $run->guard();
            $this->fail('Expected RunLimitExceededException.');
        } catch (RunLimitExceededException $e) {
            $this->assertSame(RunLimitExceededException::REASON_TOOL_CALLS, $e->reason);
        }
    }

    public function test_guard_throws_on_the_wall_clock_cap()
    {
        $run = $this->meter()->run(BudgetScope::user(1), ['max_wall_clock' => 0]);

        $this->expectException(RunLimitExceededException::class);
        $run->guard();
    }

    public function test_guard_throws_on_the_run_budget()
    {
        $run = $this->meter()->run(BudgetScope::user(1), ['budget' => 1.00]);

        $run->record([
            'provider' => 'openai', 'model' => 'gpt-4o',
            'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 0],
        ]); // costs 2.50

        $this->assertSame(0.0, $run->remaining());

        $this->expectException(BudgetExceededException::class);
        $run->guard();
    }

    public function test_guard_throws_on_a_blocking_scope_budget()
    {
        config(['ai-meter.budgets' => [['scope' => 'user', 'period' => 'month', 'limit' => 50, 'action' => 'block']]]);

        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 60,
            'scope_type' => 'user', 'scope_id' => '1', 'created_at' => now(),
        ]);

        $run = $this->meter()->run(BudgetScope::user(1), ['budget' => null]);

        $this->expectException(BudgetExceededException::class);
        $run->guard();
    }

    public function test_fail_marks_the_run_failed()
    {
        $run = $this->meter()->run(BudgetScope::user(1));
        $run->fail('boom');

        $row = AiMeterRun::first();
        $this->assertSame('failed', $row->status);
        $this->assertSame('boom', $row->error);
    }

    public function test_it_records_a_prism_response_under_a_run()
    {
        $run = $this->meter()->run(BudgetScope::user(1));
        $run->recordPrism($this->fakePrismResponse(100, 200, 'gpt-4o'), ['provider' => 'openai']);

        $call = AiMeterCall::first();
        $this->assertSame($run->id(), $call->run_id);
        $this->assertSame('prism', $call->source);
        $this->assertSame(1, AiMeterRun::first()->step_count);
    }
}
