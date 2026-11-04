<?php

namespace App\Notifications;

use App\Models\Computer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Баталгаа удахгүй дуусах төхөөрөмжүүд (систем доторх мэдэгдэл): салбарын Админ, Супер админд */
class WarrantyExpiringDigest extends Notification
{
    use Queueable;

    /** @param Collection<int, Computer> $computers */
    public function __construct(public Collection $computers) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $rows = $this->computers->map(fn (Computer $c) => $c->activityLabel().' — '.$c->warranty_until->format('Y-m-d')
            .($c->assigned_to ? ' · '.$c->assigned_to : ''));

        return [
            'title' => $this->computers->count().' төхөөрөмжийн баталгаа удахгүй дуусна',
            'body' => $rows->take(20)->implode("\n"),
            'url' => route('computers.index', ['warranty' => 'expiring']),
            'kind' => 'digest',
        ];
    }
}
