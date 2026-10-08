<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\Fixtures\FakeAiEvent;
use Nsd7\AiMeter\Tests\TestCase;

class AutoInstrumentTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        // Enable the Laravel AI adapter, pointed at our fake event class, before
        // the provider boots so the listener is registered.
        $app['config']->set('ai-meter.adapters.laravel_ai.enabled', true);
        $app['config']->set('ai-meter.adapters.laravel_ai.event', FakeAiEvent::class);
        $app['config']->set('ai-meter.pricing.prices', [
            'openai' => ['gpt-4o' => ['input' => 2.5, 'output' => 10]],
        ]);
    }

    protected function aiResponse(int $input, int $output, string $model = 'gpt-4o', string $provider = 'openai'): object
    {
        return (object) [
            'usage' => (object) ['inputTokens' => $input, 'outputTokens' => $output],
            'model' => $model,
            'provider' => $provider,
        ];
    }

    public function test_it_records_a_laravel_ai_event_automatically()
    {
        event(new FakeAiEvent($this->aiResponse(1000, 500)));

        $call = AiMeterCall::first();

        $this->assertNotNull($call);
        $this->assertSame('laravel-ai', $call->source);
        $this->assertSame('openai', $call->provider);
        $this->assertSame('gpt-4o', $call->model);
        $this->assertSame(1500, $call->total_tokens);
        // 1000/1e6*2.5 + 500/1e6*10 = 0.0075
        $this->assertEqualsWithDelta(0.0075, $call->cost_usd, 0.0000001);
    }

    public function test_it_attributes_to_the_authenticated_user()
    {
        $user = $this->makeUser();

        $this->actingAs($user);
        event(new FakeAiEvent($this->aiResponse(10, 10)));

        $call = AiMeterCall::first();
        $this->assertSame('user', $call->scope_type);
        $this->assertSame((string) $user->id, $call->scope_id);
    }

    public function test_events_without_usage_are_ignored()
    {
        event(new FakeAiEvent((object) ['model' => 'gpt-4o']));

        $this->assertSame(0, AiMeterCall::count());
    }

    public function test_prism_tap_records_a_response()
    {
        $tap = app(Meter::class)->prismTap(['provider' => 'openai']);

        // Prism calls the callback with (PendingRequest, Response).
        $tap(null, $this->fakePrismResponse(200, 300, 'gpt-4o'));

        $call = AiMeterCall::first();
        $this->assertSame('prism', $call->source);
        $this->assertSame('gpt-4o', $call->model);
        $this->assertSame(500, $call->total_tokens);
    }
}
