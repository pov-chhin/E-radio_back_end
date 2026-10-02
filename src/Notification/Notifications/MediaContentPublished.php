<?php

namespace Src\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MediaContentPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly string $title, private readonly string $mediaType) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Media content published',
            'meta' => ucfirst($this->mediaType)." — {$this->title}",
            'tone' => 'blue',
        ];
    }
}
