<?php

namespace Nsd7\AiMeter\Tests\Feature;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Events\BudgetThresholdReached;
use Nsd7\AiMeter\Facades\Meter;
use Nsd7\AiMeter\Notifications\BudgetAlert;
use Nsd7\AiMeter\Tests\TestCase;

class BudgetAlertTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('ai-meter.budgets', [
            ['scope' => 'user', 'period' => 'month', 'limit' => 10, 'action' => 'alert'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function spend(float $usd, int|string $user = 1): void
    {
        Meter::log(['provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => $usd, 'scope' => BudgetScope::user($user)]);
    }

    public function test_it_fires_once_per_threshold()
    {
        Event::fake([BudgetThresholdReached::class]);

        $this->spend(5);   // 50%
        Event::assertNotDispatched(BudgetThresholdReached::class);

        $this->spend(3.5); // 85%
        $this->spend(0.5); // 90% — already alerted at 80%
        Event::assertDispatchedTimes(BudgetThresholdReached::class, 1);
        Event::assertDispatched(BudgetThresholdReached::class, fn ($e) => $e->threshold === 0.8
            && $e->scope->key() === 'user:1'
            && abs($e->spentUsd - 8.5) < 1e-9);

        $this->spend(2);   // 110%
        Event::assertDispatchedTimes(BudgetThresholdReached::class, 2);
        Event::assertDispatched(BudgetThresholdReached::class, fn ($e) => $e->threshold === 1.0 && $e->exceeded());
    }

    public function test_a_jump_past_several_thresholds_sends_only_the_highest()
    {
        Event::fake([BudgetThresholdReached::class]);

        $this->spend(12);
        $this->spend(1);

        Event::assertDispatchedTimes(BudgetThresholdReached::class, 1);
        Event::assertDispatched(BudgetThresholdReached::class, fn ($e) => $e->threshold === 1.0);
    }

    public function test_scopes_are_alerted_independently()
    {
        Event::fake([BudgetThresholdReached::class]);

        $this->spend(9, 1);
        $this->spend(9, 2);
        $this->spend(1, 3);

        Event::assertDispatchedTimes(BudgetThresholdReached::class, 2);
    }

    public function test_it_alerts_again_in_a_new_window()
    {
        Event::fake([BudgetThresholdReached::class]);

        Carbon::setTestNow('2024-06-15 12:00:00');
        $this->spend(9);

        Carbon::setTestNow('2024-07-02 12:00:00');
        $this->spend(9);

        Event::assertDispatchedTimes(BudgetThresholdReached::class, 2);
    }

    public function test_global_budgets_count_every_call()
    {
        config()->set('ai-meter.budgets', [
            ['scope' => 'global', 'period' => 'day', 'limit' => 10, 'action' => 'alert'],
        ]);
        Event::fake([BudgetThresholdReached::class]);

        $this->spend(5, 1);
        Meter::log(['provider' => 'openai', 'model' => 'gpt-4o', 'cost_usd' => 4]); // unscoped

        Event::assertDispatched(BudgetThresholdReached::class, fn ($e) => $e->scope->type === 'global'
            && $e->threshold === 0.8);
    }

    public function test_it_can_be_disabled()
    {
        config()->set('ai-meter.alerts.enabled', false);
        Event::fake([BudgetThresholdReached::class]);

        $this->spend(20);

        Event::assertNotDispatched(BudgetThresholdReached::class);
    }

    public function test_it_mails_the_configured_address()
    {
        config()->set('ai-meter.alerts.notify.mail', 'ops@example.com');
        Notification::fake();

        $this->spend(9);

        Notification::assertSentTo(
            new AnonymousNotifiable(),
            BudgetAlert::class,
            function (BudgetAlert $notification, array $channels, AnonymousNotifiable $notifiable) {
                return $channels === ['mail']
                    && $notifiable->routes['mail'] === ['ops@example.com']
                    && str_contains($notification->subject(), 'user 1 reached 80% of its month budget');
            }
        );
    }

    public function test_it_posts_to_a_slack_webhook()
    {
        config()->set('ai-meter.alerts.notify.slack', 'https://hooks.slack.test/abc');
        Http::fake();

        $this->spend(11);

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.test/abc'
            && str_contains($request['text'], '$11.00 of a $10.00 month budget (110%, alert)'));
    }

    public function test_notification_failures_never_break_recording()
    {
        config()->set('ai-meter.alerts.notify.slack', 'https://hooks.slack.test/abc');
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->spend(11);

        $this->assertDatabaseCount('ai_meter_calls', 1);
    }

    public function test_nothing_is_sent_without_routes()
    {
        Notification::fake();

        $this->spend(11);

        Notification::assertNothingSent();
    }
}
