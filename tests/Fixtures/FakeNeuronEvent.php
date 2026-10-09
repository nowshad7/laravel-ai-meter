<?php

namespace Nsd7\AiMeter\Tests\Fixtures;

/**
 * Stands in for a Neuron AI event (e.g. InferenceStop) carrying the message /
 * response of a completed inference. Lets us test the listener without Neuron
 * installed.
 */
class FakeNeuronEvent
{
    public function __construct(public object $message)
    {
    }
}
