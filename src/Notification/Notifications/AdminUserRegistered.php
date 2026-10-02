<?php

namespace Src\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminUserRegistered extends Notification
{
    use Queueable;

    public function __construct(private readonly string $name, private readonly string $email) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New admin user registered',
            'meta' => "{$this->name} ({$this->email})",
            'tone' => 'violet',
        ];
    }
}
