<?php

namespace Nsd7\AiMeter\Tests\Fixtures;

/**
 * Stands in for a Laravel AI SDK event (e.g. AgentPrompted) carrying a
 * response with usage. Lets us test the listener without the SDK installed.
 */
class FakeAiEvent
{
    public function __construct(public object $response)
    {
    }
}
