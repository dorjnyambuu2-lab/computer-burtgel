<?php

namespace App\Notifications\Concerns;

/**
 * Имэйлийг хэрэглэгчид хариу илгээсний ДАРАА явуулна («deferred» queue: тусдаа worker процесс шаардахгүй).
 * Gmail SMTP-тэй холбогдох нь 2–4 секунд зарцуулдаг тул шууд илгээвэл товч дарсан хэрэглэгч тэр хугацаанд хүлээдэг байсан.
 * Систем доторх (database) мэдэгдэл өмнөх шигээ шууд хадгалагдана.
 * Artisan команд (өдөр тутмын тойм) болон тестэд хүлээх хэрэглэгч байхгүй тул шууд илгээнэ.
 *
 * Хэрэглэх: notification класс `implements ShouldQueue`, `use Queueable, SendsMailAfterResponse;`
 */
trait SendsMailAfterResponse
{
    public function viaConnections(): array
    {
        return [
            'mail' => app()->runningInConsole() ? 'sync' : config('queue.mail_connection', 'deferred'),
            'database' => 'sync',
        ];
    }
}
