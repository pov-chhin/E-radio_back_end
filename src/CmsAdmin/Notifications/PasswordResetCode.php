<?php

namespace Src\CmsAdmin\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\CmsAdmin\Models\User;

/**
 * The 6-digit code the mobile app's forgotten-password flow asks for. Unlike
 * AccountRecovery (a link for the website), this one is meant to be typed into
 * the app, so it carries the bare code and how long it lasts.
 */
class PasswordResetCode extends Notification
{
    public function __construct(
        public readonly string $code,
        private readonly int $validForMinutes,
    ) {}

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('app.name').' password reset code')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Use this code to reset your password:')
            ->line('**'.$this->code.'**')
            ->line("It expires in {$this->validForMinutes} ".($this->validForMinutes === 1 ? 'minute' : 'minutes').". If you didn't ask to reset your password, you can ignore this email.");
    }
}
