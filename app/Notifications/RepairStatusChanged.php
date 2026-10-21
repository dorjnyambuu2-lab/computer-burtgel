<?php

namespace App\Notifications;

use App\Models\Computer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Засварын мэдээлэл: дараалалд орсон, явц өөрчлөгдсөн, засвар дууссан.
 * Систем дотор (мэдэгдэл) болон цахим шуудангаар илгээгдэнэ.
 */
class RepairStatusChanged extends Notification
{
    use Queueable;

    public const KINDS = [
        'queued' => 'Засварын дараалалд орлоо',
        'stage' => 'Засварын явц шинэчлэгдлээ',
        'moved' => 'Засварын дарааллын байр өөрчлөгдлөө',
        'done' => 'Засвар дууслаа',
    ];

    public function __construct(
        public Computer $computer,
        public string $kind,
        public ?string $note = null,
    ) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    private function title(): string
    {
        return (self::KINDS[$this->kind] ?? 'Засварын мэдээлэл').': '.$this->computer->activityLabel();
    }

    private function lines(): array
    {
        $lines = [];
        $number = $this->computer->repairQueueNumber();

        if ($this->kind === 'done') {
            $lines[] = 'Төлөв: '.$this->computer->status->label();
        } else {
            if ($number) {
                $lines[] = 'Дарааллын дугаар: #'.$number;
            }
            if ($this->computer->repair_stage) {
                $lines[] = 'Явц: '.$this->computer->repair_stage->label();
            }
        }

        if (filled($this->note)) {
            $lines[] = 'Тайлбар: '.$this->note;
        }

        return $lines;
    }

    private function url(object $notifiable): string
    {
        return method_exists($notifiable, 'canManageComputers') && $notifiable->canManageComputers()
            ? route('computers.show', $this->computer)
            : route('my.computers.show', $this->computer->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Сайн байна уу, '.$notifiable->name.'!');

        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->action('Дэлгэрэнгүй харах', $this->url($notifiable))
            ->salutation(config('app.name'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => implode(' · ', $this->lines()),
            'url' => $this->url($notifiable),
            'computer_id' => $this->computer->id,
            'kind' => $this->kind,
        ];
    }
}
