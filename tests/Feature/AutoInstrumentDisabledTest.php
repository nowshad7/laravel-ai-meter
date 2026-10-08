<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\Fixtures\FakeAiEvent;
use Nsd7\AiMeter\Tests\TestCase;

class AutoInstrumentDisabledTest extends TestCase
{
    public function test_the_adapter_is_disabled_by_default()
    {
        $this->assertFalse(config('ai-meter.adapters.laravel_ai.enabled'));

        // No listener is registered, so dispatching the event records nothing.
        event(new FakeAiEvent((object) [
            'usage' => (object) ['inputTokens' => 100, 'outputTokens' => 100],
            'model' => 'gpt-4o',
        ]));

        $this->assertSame(0, AiMeterCall::count());
    }
}
