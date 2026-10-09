<?php

namespace Nsd7\AiMeter\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Nsd7\AiMeter\Events\BudgetThresholdReached;

/**
 * Mail / Slack notification sent when a budget threshold is reached.
 */
class BudgetAlert extends Notification
{
    public function __construct(public readonly BudgetThresholdReached $event)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->routeNotificationFor('mail', $this)) {
            $channels[] = 'mail';
        }

        if ($notifiable->routeNotificationFor(SlackWebhookChannel::class, $this)) {
            $channels[] = SlackWebhookChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject($this->subject())
            ->line($this->summary());

        if ($this->event->exceeded()) {
            $message->error()->line($this->event->budget->blocks()
                ? 'This is a blocking budget: further AI requests for this scope are being rejected.'
                : 'This is an alert-only budget: requests are still allowed.');
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(object $notifiable): array
    {
        return ['text' => ($this->event->exceeded() ? ':rotating_light: ' : ':warning: ') . $this->summary()];
    }

    public function subject(): string
    {
        $percent = (int) round($this->event->threshold * 100);

        return "[AI Meter] {$this->scopeLabel()} reached {$percent}% of its {$this->event->budget->period->value} budget";
    }

    public function summary(): string
    {
        $budget = $this->event->budget;

        return sprintf(
            'AI spend for %s is $%s of a $%s %s budget (%d%%, %s).',
            $this->scopeLabel(),
            number_format($this->event->spentUsd, 2),
            number_format($budget->limitUsd, 2),
            $budget->period->value,
            (int) round($this->event->usedFraction() * 100),
            $budget->action,
        );
    }

    protected function scopeLabel(): string
    {
        $scope = $this->event->scope;

        return $scope->id === null ? $scope->type : "{$scope->type} {$scope->id}";
    }
}
