<?php

namespace App\Notifications;

use App\Models\PasswordResetRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Нууц үгээ мартсан тухай мэдэгдэл: админых Супер админд, ажилтных салбарын Админ болон Супер админд */
class PasswordResetRequested extends Notification
{
    use Queueable;

    public function __construct(public PasswordResetRequest $resetRequest) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    private function title(): string
    {
        $user = $this->resetRequest->user;

        return 'Нууц үг сэргээх хүсэлт: '.$user->name.' ('.$user->roleLabel().')';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->resetRequest->user;

        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Сайн байна уу, '.$notifiable->name.'!')
            ->line($user->name.' ('.$user->username.') нууц үгээ мартсан тул сэргээх хүсэлт илгээлээ.')
            ->line('Хүсэлтийг шалгаад түр нууц үг олгоно уу. Хэрэглэгч нэвтэрмэгц нууц үгээ солино.')
            ->action('Хүсэлтүүдийг харах', route('password-requests.index'))
            ->salutation(config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => 'IP: '.($this->resetRequest->ip_address ?? '—'),
            'url' => route('password-requests.index'),
            'kind' => 'password_reset',
        ];
    }
}
