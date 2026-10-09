<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\Fixtures\FakeNeuronEvent;
use Nsd7\AiMeter\Tests\Fixtures\FakeNeuronMessage;
use Nsd7\AiMeter\Tests\TestCase;

class NeuronAutoInstrumentTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('ai-meter.adapters.neuron.enabled', true);
        $app['config']->set('ai-meter.adapters.neuron.event', FakeNeuronEvent::class);
        $app['config']->set('ai-meter.pricing.prices', [
            'openai' => ['gpt-4o' => ['input' => 2.5, 'output' => 10]],
        ]);
    }

    public function test_it_records_a_neuron_event_via_accessor_methods()
    {
        event(new FakeNeuronEvent(new FakeNeuronMessage(1000, 500)));

        $call = AiMeterCall::first();

        $this->assertNotNull($call);
        $this->assertSame('neuron', $call->source);
        $this->assertSame('openai', $call->provider);
        $this->assertSame('gpt-4o', $call->model);
        $this->assertSame(1500, $call->total_tokens);
        $this->assertEqualsWithDelta(0.0075, $call->cost_usd, 1e-7);
    }

    public function test_it_records_a_neuron_event_via_properties()
    {
        $message = (object) [
            'usage' => (object) ['inputTokens' => 10, 'outputTokens' => 20],
            'model' => 'gpt-4o',
            'provider' => 'openai',
        ];

        event(new FakeNeuronEvent($message));

        $call = AiMeterCall::first();
        $this->assertSame('neuron', $call->source);
        $this->assertSame(30, $call->total_tokens);
    }

    public function test_it_attributes_to_the_authenticated_user()
    {
        $user = $this->makeUser();

        $this->actingAs($user);
        event(new FakeNeuronEvent(new FakeNeuronMessage(10, 10)));

        $call = AiMeterCall::first();
        $this->assertSame('user', $call->scope_type);
        $this->assertSame((string) $user->id, $call->scope_id);
    }

    public function test_it_uses_the_default_provider_when_none_is_present()
    {
        config()->set('ai-meter.adapters.neuron.default_provider', 'anthropic');

        event(new FakeNeuronEvent(new FakeNeuronMessage(5, 5, 'claude-3-5-haiku', provider: null)));

        $this->assertSame('anthropic', AiMeterCall::first()->provider);
    }

    public function test_events_without_usage_are_ignored()
    {
        event(new FakeNeuronEvent((object) ['model' => 'gpt-4o']));

        $this->assertSame(0, AiMeterCall::count());
    }
}
