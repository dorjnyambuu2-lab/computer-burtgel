<?php

namespace App\Support;

use Illuminate\Support\Facades\Notification;
use Throwable;

class Notify
{
    /**
     * Мэдэгдэл илгээнэ. Шуудангийн сервер тохируулаагүй / унтарсан үед үйлдэл өөрөө
     * бүтэлгүйтэхгүй: алдааг логт бичээд, систем доторх мэдэгдлийг ямар ч үед хадгална.
     */
    public static function send($users, $notification): void
    {
        $users = collect($users)->filter()->unique('id')->values();

        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, $notification);
        } catch (Throwable $e) {
            report($e);

            try {
                Notification::sendNow($users, $notification, ['database']);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
