<?php

namespace App\Notifications;

use App\Models\Computer;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Админ компьютер / төхөөрөмж бүртгэх эсвэл устгахад Супер админуудад очих систем доторх мэдэгдэл.
 * Багцаар бүртгэсэн бол нэг мэдэгдэлд нэгтгэнэ.
 */
class ComputersRegistered extends Notification
{
    use Queueable;

    public const REGISTERED = 'registered';
    public const DELETED = 'deleted';

    /** @param  list<Computer>  $computers */
    public function __construct(
        public User $actor,
        public array $computers,
        public string $action = self::REGISTERED,
    ) {}

    /** Бүртгэсэн хүн Админ бол бүх идэвхтэй Супер админд илгээнэ */
    public static function dispatch(User $actor, iterable $computers, string $action = self::REGISTERED): void
    {
        $computers = collect($computers)->filter()->values()->all();

        if (! $computers || $actor->isSuperadmin()) {
            return;
        }

        $superadmins = User::query()->where('role', 'superadmin')->where('is_active', true)->get();

        Notify::send($superadmins, new self($actor, $computers, $action));
    }

    /** Админ устгасан үед (архивт шилжсэн) */
    public static function deleted(User $actor, Computer $computer): void
    {
        self::dispatch($actor, [$computer], self::DELETED);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $count = count($this->computers);
        $first = $this->computers[0];
        $deleted = $this->action === self::DELETED;
        $verb = $deleted ? 'устгалаа' : 'бүртгэлээ';

        $names = collect($this->computers)->take(5)
            ->map(fn (Computer $c) => ($deleted ? '' : $c->sequenceLabel().' ').$c->name.' ('.$c->inventory_number.')')
            ->implode("\n");

        $url = match (true) {
            // Устгасан төхөөрөмж «Устгасан» хэсэгт байна (сэргээж эсвэл бүрмөсөн устгаж болно)
            $deleted => route('computers.trash', ['q' => $first->inventory_number]),
            $count === 1 => route('computers.show', $first),
            default => route('computers.index', ['created_by' => $this->actor->id, 'sort' => 'id', 'dir' => 'desc']),
        };

        // «bat (Дархан салбар)» — аль салбарын админ болохыг харуулна
        $who = $this->actor->name.($this->actor->branch ? ' ('.$this->actor->branch->name.')' : '');

        return [
            'title' => $count === 1
                ? $who.' төхөөрөмж '.$verb.': '.$first->name
                : $who.' '.$count.' төхөөрөмж '.$verb,
            'body' => $names.($count > 5 ? "\n... болон бусад ".($count - 5) : '')
                .($deleted ? "\nУстгасан хэсгээс сэргээж болно." : ''),
            'url' => $url,
            'computer_ids' => collect($this->computers)->pluck('id')->all(),
            'kind' => $this->action,
        ];
    }
}
