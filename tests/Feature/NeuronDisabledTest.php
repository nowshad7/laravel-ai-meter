<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\Fixtures\FakeNeuronEvent;
use Nsd7\AiMeter\Tests\Fixtures\FakeNeuronMessage;
use Nsd7\AiMeter\Tests\TestCase;

class NeuronDisabledTest extends TestCase
{
    public function test_the_neuron_adapter_is_disabled_by_default()
    {
        $this->assertFalse(config('ai-meter.adapters.neuron.enabled'));

        event(new FakeNeuronEvent(new FakeNeuronMessage(100, 100)));

        $this->assertSame(0, AiMeterCall::count());
    }
}
