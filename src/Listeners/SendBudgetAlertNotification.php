<?php

namespace Nsd7\AiMeter\Listeners;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Nsd7\AiMeter\Events\BudgetThresholdReached;
use Nsd7\AiMeter\Notifications\BudgetAlert;
use Nsd7\AiMeter\Notifications\SlackWebhookChannel;
use Throwable;

/**
 * Sends the configured mail / Slack alert for a reached budget threshold. A
 * failing mail server or webhook is reported, never thrown into the AI call.
 */
class SendBudgetAlertNotification
{
    public function handle(BudgetThresholdReached $event): void
    {
        $mail = array_values(array_filter((array) config('ai-meter.alerts.notify.mail')));
        $slack = config('ai-meter.alerts.notify.slack');

        if ($mail === [] && ! $slack) {
            return;
        }

        $notifiable = new AnonymousNotifiable();

        if ($mail !== []) {
            $notifiable->route('mail', $mail);
        }

        if ($slack) {
            $notifiable->route(SlackWebhookChannel::class, $slack);
        }

        $class = config('ai-meter.alerts.notification') ?: BudgetAlert::class;

        try {
            Notification::send($notifiable, new $class($event));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
