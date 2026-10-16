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
            ->from(config('mail.from.address'), 'E-Radio AMS')
            ->subject('Your E-Radio password reset code')
            ->view('emails.password-reset-code', [
                'code' => $this->code,
                'userName' => $notifiable->name,
                'validForMinutes' => $this->validForMinutes,
            ]);
    }
}
