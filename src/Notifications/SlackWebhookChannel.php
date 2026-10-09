<?php

namespace Nsd7\AiMeter\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/**
 * Posts a notification to a Slack incoming-webhook URL, so Slack alerts work
 * without requiring laravel/slack-notification-channel.
 */
class SlackWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $url = $notifiable->routeNotificationFor(self::class, $notification);

        if (! $url || ! method_exists($notification, 'toSlackWebhook')) {
            return;
        }

        Http::post($url, $notification->toSlackWebhook($notifiable))->throw();
    }
}
