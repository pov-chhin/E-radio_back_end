<?php

namespace Src\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LiveSourceToggled extends Notification
{
    use Queueable;

    public function __construct(private readonly bool $isLive) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->isLive
            ? ['title' => 'Live source connected', 'meta' => 'Studio A is now on air', 'tone' => 'green']
            : ['title' => 'Live source stopped', 'meta' => 'AutoDJ fallback is now on air', 'tone' => 'amber'];
    }
}
