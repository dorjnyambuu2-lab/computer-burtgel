<?php

namespace App\Notifications;

use App\Models\Computer;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Компьютер бүртгэхэд хариуцагчаар бичсэн хүн системд байхгүй тул шинэ ажилтан автоматаар үүссэн
 * (Computer::createHolderUser). Нууц үг нь санамсаргүй тул нэвтрэхийн тулд нууц үг тохируулж өгөх ёстойг
 * салбарын Админууд, Супер админуудад сануулна (үүсгэсэн хүнээс бусад).
 */
class HolderUserCreated extends Notification
{
    use Queueable;

    public function __construct(public User $employee, public Computer $computer) {}

    public static function dispatch(User $actor, User $employee, Computer $computer): void
    {
        $recipients = User::query()->where('is_active', true)
            ->where(fn ($q) => $q->where('role', 'superadmin')
                ->orWhere(fn ($w) => $w->where('role', 'admin')->where('branch_id', $employee->branch_id)))
            ->whereKeyNot($actor->id)
            ->get();

        Notify::send($recipients, new self($employee, $computer));
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Шинэ ажилтан автоматаар нэмэгдлээ: '.trim($this->employee->surname.' '.$this->employee->name),
            'body' => $this->computer->inventory_number.' «'.$this->computer->name.'»-ийн хариуцагчаар бүртгэгдсэн. '
                .'Нэвтрэх нэр: '.$this->employee->username.'. Нэвтрэхийн тулд нууц үг тохируулж өгнө үү (нэр буруу бол засна уу).',
            'url' => route('users.edit', $this->employee),
            'kind' => 'holder_created',
        ];
    }
}
