<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Nsd7\AiMeter\Tests\TestCase;

class ApiBudgetDisabledTest extends TestCase
{
    public function test_the_budget_api_is_not_registered_by_default()
    {
        $this->assertFalse(config('ai-meter.features.api'));

        // The route is not registered, so even an authenticated request 404s.
        $this->actingAs($this->makeUser())
            ->getJson('/ai-meter/api/budget')
            ->assertNotFound();
    }
}
