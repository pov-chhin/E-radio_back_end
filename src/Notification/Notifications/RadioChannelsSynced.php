<?php

namespace Src\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RadioChannelsSynced extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $added,
        private readonly int $updated,
        private readonly int $stations,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Radio channels synced',
            'meta' => "Added {$this->added}, updated {$this->updated} (from {$this->stations} station(s))",
            'tone' => 'green',
        ];
    }
}
