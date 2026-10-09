<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Tests\TestCase;

class PruneCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function seedCall(Carbon $at): void
    {
        AiMeterCall::create([
            'provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 1,
            'created_at' => $at,
        ]);
    }

    public function test_it_prunes_records_older_than_the_retention_window()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');
        config()->set('ai-meter.prune.keep_days', 30);

        $this->seedCall(Carbon::parse('2024-06-14 12:00:00')); // 1 day old — kept
        $this->seedCall(Carbon::parse('2024-04-01 12:00:00')); // ~75 days — pruned
        $this->seedCall(Carbon::parse('2024-05-01 12:00:00')); // ~45 days — pruned

        $this->artisan('ai-meter:prune')
            ->expectsOutputToContain('Pruned 2 AI Meter record(s) older than 30 day(s).')
            ->assertSuccessful();

        $this->assertSame(1, AiMeterCall::count());
    }

    public function test_the_days_option_overrides_the_config()
    {
        Carbon::setTestNow('2024-06-15 12:00:00');
        config()->set('ai-meter.prune.keep_days', 90);

        $this->seedCall(Carbon::parse('2024-06-10 12:00:00')); // 5 days — kept
        $this->seedCall(Carbon::parse('2024-06-01 12:00:00')); // 14 days — pruned at --days=7

        $this->artisan('ai-meter:prune', ['--days' => 7])->assertSuccessful();

        $this->assertSame(1, AiMeterCall::count());
    }

    public function test_retention_can_be_disabled()
    {
        config()->set('ai-meter.prune.keep_days', 0);

        $this->seedCall(Carbon::now()->subYears(5));

        $this->artisan('ai-meter:prune')
            ->expectsOutputToContain('Retention is disabled')
            ->assertSuccessful();

        $this->assertSame(1, AiMeterCall::count());
    }
}
