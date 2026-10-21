<?php

namespace App\Notifications;

use App\Models\Computer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Засварт байгаа компьютеруудын өдөр тутмын жагсаалт (#1, #2 ...) админуудад */
class RepairQueueDigest extends Notification
{
    use Queueable;

    /** @param Collection<int, Computer> $computers дарааллаар эрэмбэлсэн */
    public function __construct(public Collection $computers) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    private function title(): string
    {
        return 'Засварын дараалал: '.$this->computers->count().' компьютер ('.now()->format('Y-m-d').')';
    }

    /** @return list<string> */
    private function rows(): array
    {
        return $this->computers->values()->map(function (Computer $c, int $i) {
            $days = $c->repair_queued_at ? (int) $c->repair_queued_at->diffInDays(now()) : null;

            return '#'.($i + 1).' '.$c->activityLabel()
                .' — '.($c->repair_stage?->label() ?? 'Дараалалд')
                .($c->assigned_to ? ' · '.$c->assigned_to : '')
                .($days !== null ? ' · '.$days.' хоног' : '');
        })->all();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->greeting('Сайн байна уу, '.$notifiable->name.'!');

        if ($this->computers->isEmpty()) {
            $mail->line('Одоогоор засварт байгаа компьютер алга.');
        } else {
            $mail->line('Засварт байгаа компьютерууд дарааллаараа:');
            foreach ($this->rows() as $row) {
                $mail->line($row);
            }
        }

        return $mail->action('Засварын дараалал', route('repairs.index'))->salutation(config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => implode("\n", array_slice($this->rows(), 0, 20)),
            'url' => route('repairs.index'),
            'kind' => 'digest',
        ];
    }
}
