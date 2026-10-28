<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Models\ComputerTransfer;
use App\Models\User;
use App\Support\RepairService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * IT ажилтны (admin / superadmin) утсаар QR уншуулж хийдэг үйлдлүүд:
 * QR уншуулах, ажилтанд олгох, буцаан авах, засварт бүртгэх.
 */
class ComputerActionController extends Controller
{
    /** QR уншуулах хуудас (камер) */
    public function scan(): View
    {
        return view('computers.scan');
    }

    /**
     * QR-аас уншсан текстээр компьютерийг олж мэдээллийн хуудас руу шилжүүлнэ.
     * QR дотор URL, бүртгэлийн дугаар, эсвэл ID байсан ч ажиллана.
     */
    public function resolve(Request $request): RedirectResponse
    {
        $code = trim((string) $request->query('code'));
        $computer = $code !== '' ? $this->findByCode($code) : null;

        if (! $computer) {
            return redirect()->route('computers.scan')
                ->with('error', 'Компьютер олдсонгүй: '.Str::limit($code, 60));
        }

        return redirect()->route('computers.show', $computer);
    }

    /** Ажилтанд олгох */
    public function assign(Request $request, Computer $computer): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', User::assignableRule($request->user())],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'user_id.required' => 'Ажилтнаа сонгоно уу.',
            'user_id.exists' => 'Сонгосон хэрэглэгч идэвхгүй эсвэл олдсонгүй.',
        ]);

        if ($this->hasPendingTransfer($computer)) {
            return $this->pendingTransferError();
        }

        $user = User::findOrFail($data['user_id']);

        if ((int) $computer->assigned_user_id === $user->id) {
            return back()->withErrors(['user_id' => 'Энэ компьютер аль хэдийн '.$user->name.'-д оноогдсон байна.']);
        }

        DB::transaction(function () use ($computer, $user, $request, $data) {
            // Өөр ажилтнаас шилжүүлж байгаа бол эхлээд IT-д буцаан авсныг бүртгэнэ (ажилтнаас ажилтанд шууд биш)
            if ($computer->assigned_user_id || filled($computer->assigned_to)) {
                $this->log($computer, $request, 'returned', $computer->assigned_user_id,
                    'IT-аар дамжуулан шилжүүлэв'.($computer->assigned_user_id ? '' : ' (хариуцагч: '.$computer->assigned_to.')'));
            }

            // Хариуцагч солигдох үед model нь assigned_to-г синк хийж, "хүлээн авсан" баталгааг арилгана
            $computer->assigned_user_id = $user->id;
            $computer->status = $this->status('Ашиглагдаж байгаа');
            $computer->save();
            $this->log($computer, $request, 'assigned', $user->id, $data['note'] ?? null);

            // Иж бүрдлийн дагалдах хэрэгслүүд (дэлгэц, mouse ...) компьютертэйгээ хамт олгогдоно
            $items = $this->kitMembers($computer);
            Computer::$transferring = true; // иж бүрдлийн нэг хэсэг тул Супер админы «шууд солих» хоригийг алгасна
            try {
                foreach ($items as $item) {
                    $item->assigned_user_id = $user->id;
                    $item->status = ComputerStatus::Active;
                    $item->save();
                    $this->log($item, $request, 'assigned', $user->id, 'Иж бүрдлээр: '.$computer->inventory_number);
                }
            } finally {
                Computer::$transferring = false;
            }

            $this->completeReceivedTransfers([$computer->id, ...$items->pluck('id')], $user, $request);
        });

        return back()->with('success', $computer->activityLabel().' → '.$user->name.'-д олголоо.');
    }

    /** Буцаан авах (хариуцагчийг цуцалж, агуулахад оруулна) */
    public function takeBack(Request $request, Computer $computer): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $computer->assigned_user_id && blank($computer->assigned_to)) {
            return back()->withErrors(['action' => 'Энэ компьютер хэнд ч оноогдоогүй байна.']);
        }

        if ($this->hasPendingTransfer($computer)) {
            return $this->pendingTransferError();
        }

        $previousUserId = $computer->assigned_user_id;
        $legacyHolder = $previousUserId ? null : $computer->assigned_to; // системийн хэрэглэгчтэй холбогдоогүй текст

        $note = trim(($legacyHolder ? 'Хариуцагч: '.$legacyHolder.'. ' : '').($data['note'] ?? ''));

        DB::transaction(function () use ($computer, $request, $previousUserId, $note) {
            $computer->assigned_user_id = null;
            $computer->assigned_to = null;
            $computer->status = $this->status('Агуулахад');
            $computer->save();
            $this->log($computer, $request, 'returned', $previousUserId, $note);

            // Иж бүрдлийн дагалдах хэрэгслүүд хамт агуулахад
            foreach ($this->kitMembers($computer) as $item) {
                $itemHolder = $item->assigned_user_id;
                $item->assigned_user_id = null;
                $item->assigned_to = null;
                $item->status = ComputerStatus::InStorage;
                $item->save();
                $this->log($item, $request, 'returned', $itemHolder, 'Иж бүрдлээр: '.$computer->inventory_number);
            }
        });

        return back()->with('success', $computer->activityLabel().' буцаан авч, агуулахад бүртгэлээ.');
    }

    /**
     * Үндсэн компьютерийн иж бүрдлийн хэрэгслүүд (олгох, буцаахад хамт хөдөлнө).
     * Засварт, актласан хэрэгсэл хөдлөхгүй; дагалдах хэрэгсэл өөрөө бол хоосон.
     */
    private function kitMembers(Computer $computer)
    {
        if ($computer->kit_parent_id) {
            return collect();
        }

        return $computer->kitItems()
            ->whereNotIn('status', [ComputerStatus::InRepair, ComputerStatus::Decommissioned])
            ->get();
    }

    /** Засварт бүртгэх (тайлбар заавал) */
    public function repair(Request $request, Computer $computer): RedirectResponse
    {
        $request->merge(['cost' => \App\Support\Money::parse($request->input('cost'))]);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'note.required' => 'Засварын тайлбараа бичнэ үү.',
            'note.min' => 'Тайлбар хэт богино байна.',
            'cost.numeric' => 'Зардлыг тоогоор оруулна уу.',
        ]);

        // Дарааллын төгсгөлд оруулж, хариуцагч болон админуудад мэдэгдэл илгээнэ
        app(RepairService::class)->start($computer, $request->user(), $data['note'], $data['cost'] ?? null);

        return back()->with('success', $computer->activityLabel().' засварт бүртгэгдэж, дараалалд #'.$computer->repairQueueNumber().' орлоо.');
    }

    /**
     * Төхөөрөмж олгох хуудас: ажилтан сонгоод, чөлөөтэй төхөөрөмжүүдээс
     * (процессор, дэлгэц, mouse, keyboard, printer ...) олныг нэг дор олгоно.
     */
    public function bulkForm(Request $request): View
    {
        // Супер админд төхөөрөмж олгохгүй
        // Ажилтан бүрийн одоогийн төхөөрөмжийн тоо (сонгох жагсаалтад)
        $all = User::query()->assignableBy($request->user())->where('role', '!=', 'superadmin')
            ->withCount(['computers' => fn ($q) => $q->counted()])->orderBy('name')->get();

        // Алба хэлтсээр шүүнэ ('none' = хэлтэсгүй); жагсаалтад хэлтсээр бүлэглэж харуулна
        $departments = $all->pluck('department')->filter()->unique()->sort()->values();
        $department = (string) $request->query('department', '');
        $employees = $department === ''
            ? $all
            : $all->filter(fn ($u) => $department === 'none' ? blank($u->department) : $u->department === $department);

        $selected = $request->filled('user_id')
            ? $all->firstWhere('id', (int) $request->query('user_id'))
            : null;

        // Ангиллаар бүлэглэнэ ("Ангилаагүй" нь 'none')
        $free = $this->availableComputers()->get();
        $available = $free->groupBy(fn ($c) => $c->category?->value ?? 'none');
        // Иж бүрдэл: үндсэн компьютерийг сонгоход түүний чөлөөтэй хэрэгслүүд хамт сонгогдоно [үндсэн id => хэрэгслийн тоо]
        $kitSizes = $free->whereNotNull('kit_parent_id')->countBy('kit_parent_id')->all();

        $current = $selected
            ? Computer::query()->where('assigned_user_id', $selected->id)->orderBy('name')->get()
            : collect();

        return view('computers.bulk-assign', compact('all', 'employees', 'departments', 'department', 'selected', 'available', 'current', 'kitSizes'));
    }

    /** Сонгосон төхөөрөмжүүдийг ажилтанд нэг дор олгоно */
    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', User::assignableRule($request->user())->whereNot('role', 'superadmin')],
            'computer_ids' => ['required', 'array', 'min:1'],
            'computer_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'user_id.required' => 'Ажилтнаа сонгоно уу.',
            'user_id.exists' => 'Сонгосон хэрэглэгч идэвхгүй эсвэл олдсонгүй.',
            'computer_ids.required' => 'Олгох төхөөрөмжөө дор хаяж нэгийг сонгоно уу.',
            'computer_ids.min' => 'Олгох төхөөрөмжөө дор хаяж нэгийг сонгоно уу.',
        ]);

        $user = User::findOrFail($data['user_id']);
        $ids = array_values(array_unique(array_map('intval', $data['computer_ids'])));

        $given = [];

        DB::transaction(function () use ($ids, $user, $request, $data, &$given) {
            // Зөвхөн яг одоо чөлөөтэй хэвээр байгаа төхөөрөмжийг олгоно (өөр админ өмнө нь олгосон бол алгасна)
            $computers = $this->availableComputers()->whereIn('id', $ids)->lockForUpdate()->get();

            foreach ($computers as $computer) {
                $computer->assigned_user_id = $user->id; // model нь assigned_to-г синк хийж, баталгааг арилгана
                $computer->status = $this->status('Ашиглагдаж байгаа');
                $computer->save();

                $this->log($computer, $request, 'assigned', $user->id, $data['note'] ?? null);
                $given[] = $computer;
            }

            $this->completeReceivedTransfers(array_map(fn ($c) => $c->id, $given), $user, $request);
        });

        if ($given === []) {
            return back()->withInput()->withErrors([
                'computer_ids' => 'Сонгосон төхөөрөмжүүд чөлөөтэй биш байна (өөрт оноогдсон, засварт эсвэл актлагдсан).',
            ]);
        }

        $skipped = count($ids) - count($given);
        $names = collect($given)->map(fn ($c) => $c->activityLabel())->take(6)->implode(', ');

        $message = $user->name.'-д '.count($given).' төхөөрөмж олголоо: '.$names
            .(count($given) > 6 ? ' ...' : '').'.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' нь чөлөөтэй биш байсан тул алгасав.';
        }

        return redirect()->route('computers.bulk-assign', ['user_id' => $user->id])->with('success', $message);
    }

    // ───────────────────────── туслах функцүүд ─────────────────────────

    /** Олгож болох төхөөрөмжүүд: хариуцагчгүй, засварт ба актлагдаагүй */
    private function availableComputers()
    {
        $blocked = collect(ComputerStatus::cases())
            ->filter(fn ($case) => in_array($case->label(), ['Засварт', 'Актласан'], true))
            ->map(fn ($case) => $case->value)
            ->values()
            ->all();

        return Computer::query()
            ->whereNull('assigned_user_id')
            ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', ''))
            ->when($blocked !== [], fn ($q) => $q->whereNotIn('status', $blocked))
            ->orderBy('name');
    }


    private function findByCode(string $code): ?Computer
    {
        // 0) Давтагдашгүй QR дугаар (10 оронтой тоо)
        if (preg_match('/^\d{10}$/', $code) && ($byQr = Computer::where('qr_code', $code)->first())) {
            return $byQr;
        }

        // 1) QR дотор URL байвал замаас /computers/{id}-г салгана (хост нь ямар ч байж болно)
        if (preg_match('~/computers/(\d+)(?:[/?#]|$)~', $code, $m)) {
            return Computer::find((int) $m[1]);
        }

        // 2) Бүртгэлийн дугаар (жишээ нь PC-20261019-001)
        if ($byInventory = Computer::where('inventory_number', $code)->first()) {
            return $byInventory;
        }

        // 3) Зөвхөн тоо бол ID
        return ctype_digit($code) ? Computer::find((int) $code) : null;
    }

    /**
     * Төлөвийг шошгоор нь олно ("Засварт", "Агуулахад" ...), учир нь ComputerStatus-ийн
     * case-ийн нэрийг энд хатуу бичихгүй байх зорилготой.
     */
    private function status(string $label): ComputerStatus
    {
        foreach (ComputerStatus::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        throw new \RuntimeException("ComputerStatus дотор «{$label}» шошготой төлөв олдсонгүй.");
    }

    private function hasPendingTransfer(Computer $computer): bool
    {
        return ComputerTransfer::where('computer_id', $computer->id)
            ->where('status', 'pending')
            ->exists();
    }

    private function pendingTransferError(): RedirectResponse
    {
        return back()->withErrors([
            'action' => 'Энэ төхөөрөмжийг ажилтан IT-д хүлээлгэн өгөх хүсэлт илгээсэн байна. Эхлээд «Төхөөрөмж шилжүүлэх» хэсэгт хүлээн авна уу.',
        ]);
    }

    /** IT-д хүлээн авсан (received) шилжүүлгийг энэ олголтоор дуусгана */
    private function completeReceivedTransfers(array $computerIds, User $user, Request $request): void
    {
        if ($computerIds === []) {
            return;
        }

        ComputerTransfer::query()->whereIn('computer_id', $computerIds)->where('status', 'received')->update([
            'status' => 'completed',
            'issued_to_user_id' => $user->id,
            'issued_by' => $request->user()->id,
            'completed_at' => now(),
        ]);
    }

    private function log(Computer $computer, Request $request, string $type, ?int $userId, ?string $note): void
    {
        ComputerEvent::create([
            'computer_id' => $computer->id,
            'actor_id' => $request->user()->id,
            'user_id' => $userId,
            'type' => $type,
            'note' => filled($note) ? trim($note) : null,
        ]);
    }
}