<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Түр нууц үгийг хэрэглэгчийн имэйл рүү: нэвтэрмэгц шинэ нууц үг солих шаардлагатай. Зөвхөн имэйлээр (өгөгдлийн санд хадгалахгүй) */
class TemporaryPasswordIssued extends Notification
{
    use Queueable;

    public function __construct(private string $password) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Түр нууц үг · '.config('app.name'))
            ->greeting('Сайн байна уу, '.$notifiable->name.'!')
            ->line('Таны хүсэлтээр түр нууц үг олголоо.')
            ->line('Нэвтрэх нэр: '.$notifiable->username)
            ->line('Түр нууц үг: '.$this->password)
            ->action('Нэвтрэх', route('login'))
            ->line('Нэвтэрмэгц шинэ нууц үг тохируулна. Хэрэв та хүсэлт илгээгээгүй бол админд мэдэгдэнэ үү.')
            ->salutation(config('app.name'));
    }
}
