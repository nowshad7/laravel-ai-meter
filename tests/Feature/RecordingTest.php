<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class RecordingTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('ai-meter.pricing.prices', [
            'openai' => [
                'gpt-4o' => ['input' => 2.5, 'output' => 10],
                'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.6],
            ],
        ]);
    }

    protected function meter(): Meter
    {
        return app(Meter::class);
    }

    public function test_it_computes_cost_and_persists_a_call()
    {
        $this->meter()->log([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 0],
            'scope' => BudgetScope::user(7),
        ]);

        $call = AiMeterCall::first();

        $this->assertNotNull($call);
        $this->assertSame('openai', $call->provider);
        $this->assertSame('gpt-4o', $call->model);
        $this->assertSame(2.5, $call->cost_usd);
        $this->assertSame(1_000_000, $call->total_tokens);
        $this->assertSame('user', $call->scope_type);
        $this->assertSame('7', $call->scope_id);
    }

    public function test_it_records_a_prism_response()
    {
        $this->meter()->recordPrism($this->fakePrismResponse(1000, 2000, 'gpt-4o-mini'), [
            'provider' => 'openai',
        ]);

        $call = AiMeterCall::first();

        $this->assertSame('prism', $call->source);
        $this->assertSame('gpt-4o-mini', $call->model);
        $this->assertSame(3000, $call->total_tokens);
        // 1000/1e6*0.15 + 2000/1e6*0.6 = 0.00135
        $this->assertEqualsWithDelta(0.00135, $call->cost_usd, 0.0000001);
    }

    public function test_it_redacts_secrets_in_stored_io()
    {
        $this->meter()->log([
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            'input' => 'My key is sk-ABCDEFGHIJKLMNOePqrstuv and email jane@example.com',
        ]);

        $input = AiMeterCall::first()->input;

        $this->assertStringNotContainsString('sk-ABCDEFGHIJKLMNOePqrstuv', $input);
        $this->assertStringNotContainsString('jane@example.com', $input);
        $this->assertStringContainsString('[redacted]', $input);
    }

    public function test_store_io_can_be_disabled()
    {
        config(['ai-meter.recording.store_io' => false]);

        $this->meter()->log([
            'provider' => 'openai', 'model' => 'gpt-4o',
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            'input' => 'secret prompt', 'output' => 'secret answer',
        ]);

        $call = AiMeterCall::first();
        $this->assertNull($call->input);
        $this->assertNull($call->output);
    }

    public function test_unknown_models_use_the_fallback_price()
    {
        config(['ai-meter.pricing.unknown_model' => ['input' => 1, 'output' => 2]]);

        $this->meter()->log([
            'provider' => 'acme', 'model' => 'totally-unknown',
            'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 1_000_000],
        ]);

        // 1 + 2 = 3
        $this->assertSame(3.0, AiMeterCall::first()->cost_usd);
    }
}
