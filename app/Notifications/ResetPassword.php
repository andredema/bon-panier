<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Courriel de réinitialisation, en français, envoyé par la file d'attente. */
class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject(__('app.reset.mail_subject'))
            ->greeting(__('app.reset.mail_greeting', ['name' => $notifiable->name]))
            ->line(__('app.reset.mail_intro'))
            ->action(__('app.reset.mail_action'), $url)
            ->line(__('app.reset.mail_expire', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('app.reset.mail_ignore'))
            ->salutation(__('app.reset.mail_salutation'));
    }
}
