<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

class PasswordResetCode extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(#[SensitiveParameter] public string $code)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Your Axis password reset code')
            ->greeting('Hello,')
            ->line('We received a request to reset your Axis Management password.')
            ->line('Your verification code is: '.$this->code)
            ->line('This code expires in '.$expireMinutes.' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
