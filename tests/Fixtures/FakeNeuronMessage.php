<?php

namespace Nsd7\AiMeter\Tests\Fixtures;

use Nsd7\AiMeter\Core\Data\TokenUsage;

/**
 * A Neuron-style assistant message exposing usage/model via accessor methods
 * (getUsage()/getModel()) rather than public properties, to exercise the
 * defensive UsageReader path.
 */
class FakeNeuronMessage
{
    public function __construct(
        protected int $input,
        protected int $output,
        protected string $model = 'gpt-4o',
        public ?string $provider = 'openai',
    ) {
    }

    public function getUsage(): object
    {
        return (object) ['inputTokens' => $this->input, 'outputTokens' => $this->output];
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
