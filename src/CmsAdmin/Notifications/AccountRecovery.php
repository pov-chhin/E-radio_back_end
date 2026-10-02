<?php

namespace Src\CmsAdmin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\CmsAdmin\Models\User;

class AccountRecovery extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Covers both "forgot username" and "forgot password" in one email,
     * since a customer who can't remember one often can't remember the
     * other either.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $url = url(route('customer.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Recover your '.config('app.name').' account')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your username is: '.$notifiable->username)
            ->line('Click below to set a new password. This link expires in 60 minutes.')
            ->action('Reset Password', $url)
            ->line('If you did not request this, no further action is required.');
    }
}
