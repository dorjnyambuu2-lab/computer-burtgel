<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Models\ComputerTransfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Төхөөрөмж шилжүүлэх — Супер админ (бүх салбар) ба салбарын Админ (өөрийн салбар), ажилтан эрхгүй.
 * Төхөөрөмж иж бүрдлээр нь (үндсэн компьютер + дагалдах хэрэгсэл) шилжинэ:
 *   - Админ: ажилтанд байгаа төхөөрөмжийг өөрийн салбарын өөр ажилтанд
 *   - Супер админ: аль ч төхөөрөмжийг (ажилтанд, хэлтэст, агуулахад байгаа) ажилтан, админ, алба хэлтэс
 *     эсвэл агуулах руу (хэрэглэгч рүү бол төхөөрөмжийн салбарын хэрэглэгчид)
 * Шилжүүлэг бүр түүхэнд (computer_transfers, computer_events) бичигдэнэ.
 * Өмнө ажилтнаас ирсэн шийдвэрлэгдээгүй хүсэлтийг (pending → received → completed) мөн шийдвэрлэнэ.
 */
class ComputerTransferController extends Controller
{
    /** Шилжүүлэх чиглэл: Супер админд бүгд, Админд зөвхөн хэрэглэгч (ажилтан) */
    public const DESTINATIONS = [
        'user' => 'Ажилтан, админ',
        'department' => 'Алба хэлтэс',
        'storage' => 'Агуулах',
    ];

    /** Төхөөрөмжийг (иж бүрдлээр нь) шилжүүлэх */
    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $destinations = $me->isSuperadmin() ? array_keys(self::DESTINATIONS) : ['user'];
        $request->mergeIfMissing(['destination' => 'user']);

        $data = $request->validate([
            'computer_id' => ['required', 'integer'],
            'destination' => ['required', Rule::in($destinations)],
            'to_user_id' => ['required_if:destination,user', 'nullable', 'integer', User::assignableRule($me)],
            'department' => ['required_if:destination,department', 'nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'computer_id.required' => 'Шилжүүлэх төхөөрөмжөө сонгоно уу.',
            'destination.in' => 'Шилжүүлэх чиглэлээ сонгоно уу.',
            'to_user_id.required_if' => 'Шилжүүлэн авах хэрэглэгчээ сонгоно уу.',
            'to_user_id.exists' => 'Сонгосон хэрэглэгч олдсонгүй эсвэл идэвхгүй байна.',
            'department.required_if' => 'Шилжүүлэх алба хэлтсээ сонгоно уу.',
        ]);
        $destination = $data['destination'];
        $department = trim((string) ($data['department'] ?? ''));

        // Өөрийн салбарын (BranchScope) төхөөрөмж; Админ зөвхөн ажилтанд оноогдсоныг шилжүүлнэ
        $computer = Computer::query()->whereKey($data['computer_id'])
            ->when(! $me->isSuperadmin(), fn ($q) => $q->whereNotNull('assigned_user_id'))
            ->first();
        if (! $computer) {
            return back()->withInput()->withErrors(['computer_id' => 'Төхөөрөмж олдсонгүй эсвэл ажилтанд оноогдоогүй байна. Оноогдоогүй төхөөрөмжийг «Төхөөрөмж олгох»-оор олгоно.']);
        }
        if (in_array($computer->status, [ComputerStatus::InRepair, ComputerStatus::Decommissioned], true)) {
            return back()->withInput()->withErrors(['computer_id' => $computer->activityLabel().' «'.$computer->status->label().'» төлөвтэй тул шилжүүлэх боломжгүй.']);
        }
        if (ComputerTransfer::where('computer_id', $computer->id)->open()->exists()) {
            return back()->withInput()->withErrors(['computer_id' => 'Энэ төхөөрөмжид шийдвэрлэгдээгүй хүсэлт байна. Эхлээд доорх жагсаалтаас шийдвэрлэнэ үү.']);
        }

        $next = null;
        if ($destination === 'user') {
            // Супер админ ажилтан эсвэл админд, Админ зөвхөн ажилтанд шилжүүлнэ
            $next = User::findOrFail($data['to_user_id']);
            if (! in_array($next->role, $me->isSuperadmin() ? ['employee', 'admin'] : ['employee'], true)) {
                return back()->withInput()->withErrors(['to_user_id' => 'Сонгосон хэрэглэгчид шилжүүлэх боломжгүй. Ажилтан'.($me->isSuperadmin() ? ' эсвэл админ' : '').' сонгоно уу.']);
            }
            if ((int) $computer->assigned_user_id === $next->id) {
                return back()->withInput()->withErrors(['to_user_id' => 'Төхөөрөмж аль хэдийн энэ хэрэглэгчид байна. Өөр хэрэглэгч сонгоно уу.']);
            }
            // Супер админ бүх салбарын төхөөрөмжийг шилжүүлнэ, гэхдээ зөвхөн тухайн төхөөрөмжийн салбарын хэрэглэгчид
            if ($computer->branch_id && (int) $next->branch_id !== (int) $computer->branch_id) {
                return back()->withInput()->withErrors(['to_user_id' => 'Шилжүүлэн авах хэрэглэгч төхөөрөмжийн салбарт ('.($computer->branch?->name ?? '—').') харьяалагдах ёстой.']);
            }
        } elseif ($destination === 'department' && ! $computer->assigned_user_id && $computer->department === $department && $computer->status !== ComputerStatus::InStorage) {
            return back()->withInput()->withErrors(['department' => 'Төхөөрөмж аль хэдийн «'.$department.'» хэлтэст байна.']);
        } elseif ($destination === 'storage' && ! $computer->assigned_user_id && $computer->status === ComputerStatus::InStorage) {
            return back()->withInput()->withErrors(['destination' => 'Төхөөрөмж аль хэдийн агуулахад байна.']);
        }

        $from = $computer->assignedUser;
        $fromLabel = $this->placeLabel($computer);
        $toLabel = match ($destination) {
            'department' => 'Алба: '.$department,
            'storage' => 'Агуулах',
            default => $next->name,
        };

        // Иж бүрдлээр нь: үндсэн компьютер + дагалдах хэрэгслүүд (ижил эзэмшигчтэй, шилжүүлж болохуйц)
        $set = $this->kitSet($computer);
        $skipped = Computer::query()->kitOf($computer)->where('assigned_user_id', $computer->assigned_user_id)->count() - $set->count();

        Computer::$transferring = true;
        try {
            DB::transaction(function () use ($set, $from, $fromLabel, $next, $toLabel, $destination, $department, $request, $data) {
                $kitNote = $set->count() > 1 ? ' [иж бүрдлээр]' : '';
                $reason = filled($data['reason'] ?? null) ? ' '.$data['reason'] : '';
                $note = 'Шилжүүлсэн'.$kitNote.': '.$fromLabel.' → '.$toLabel.'.'.$reason;

                foreach ($set as $item) {
                    if ($from) {
                        $this->log($item->id, $request, 'returned', $from->id, $destination === 'storage' ? $note : 'Шилжүүлэхээр буцаан авсан'.$kitNote.($reason ? ':'.$reason : ''));
                    }

                    // model нь хариуцагч солигдоход assigned_to, овог нэрийг синк хийж, «хүлээн авсан» баталгааг арилгана
                    $item->assigned_user_id = $next?->id;
                    if (! $next) {
                        $item->assigned_to = null;
                    }
                    if ($destination === 'department') {
                        $item->department = $department;
                    }
                    $item->status = $destination === 'storage' ? ComputerStatus::InStorage : ComputerStatus::Active;
                    $item->save();

                    if ($destination !== 'storage') {
                        $this->log($item->id, $request, 'assigned', $next?->id, $note);
                    } elseif (! $from) {
                        $this->log($item->id, $request, 'returned', null, $note);
                    }

                    ComputerTransfer::create([
                        'computer_id' => $item->id,
                        'from_user_id' => $from?->id,
                        'from_label' => $from ? null : $fromLabel,
                        'to_user_id' => $next?->id,
                        'destination' => $next ? 'user' : $destination,
                        'to_department' => $destination === 'department' ? $department : null,
                        'issued_to_user_id' => $next?->id,
                        'issued_by' => $request->user()->id,
                        'reason' => $data['reason'] ?? null,
                        'status' => 'transferred',
                        'completed_at' => now(),
                    ]);
                }
            });
        } finally {
            Computer::$transferring = false;
        }

        $what = $set->count() > 1
            ? 'Иж бүрдэл ('.$set->count().' төхөөрөмж: '.$set->map->activityLabel()->implode(', ').')'
            : $computer->activityLabel();

        return back()->with('success', $what.': '.$fromLabel.' → '.$toLabel.' руу шилжүүллээ.'
            .($skipped > 0 ? ' '.$skipped.' нь засварт/актласан эсвэл шийдвэрлэгдээгүй хүсэлттэй тул шилжээгүй.' : ''))
            // Шилжээгүй төхөөрөмж байвал уншиж амжихаар алга болгохгүй
            ->with('success_autohide', $skipped > 0 ? null : 3000);
    }

    /** Төхөөрөмж одоо хаана байгаа: хариуцагч, агуулах, алба хэлтэс */
    private function placeLabel(Computer $computer): string
    {
        return match (true) {
            (bool) $computer->assigned_user_id => $computer->assignedUser?->name ?? '—',
            $computer->status === ComputerStatus::InStorage => 'Агуулах',
            filled($computer->department) => 'Алба: '.$computer->department,
            filled($computer->assigned_to) => $computer->assigned_to,
            default => 'Хариуцагчгүй',
        };
    }

    /**
     * Сонгосон төхөөрөмжийн иж бүрдэл: ижил эзэмшигчтэй, засварт/актласан биш, нээлттэй хүсэлтгүй
     * бүх гишүүд (үндсэн компьютер эхэнд, дараа нь дэд дугаараар).
     */
    private function kitSet(Computer $computer)
    {
        return Computer::query()->kitOf($computer)
            ->where('assigned_user_id', $computer->assigned_user_id)
            ->whereNotIn('status', [ComputerStatus::InRepair, ComputerStatus::Decommissioned])
            ->whereNotIn('id', ComputerTransfer::query()->open()->select('computer_id'))
            ->orderByRaw('kit_parent_id IS NOT NULL')->orderBy('kit_index')->orderBy('id')
            ->get();
    }

    public function index(Request $request): View
    {
        $me = $request->user();

        // Хайлт: «#12» бол дэс дугаараар, бусад үед төхөөрөмж, ажилтнаар
        $term = trim((string) $request->query('q'));
        $number = \App\Support\RowNumber::parse($term);

        $query = ComputerTransfer::with(['computer', 'fromUser', 'toUser', 'issuedTo', 'receiver'])
            ->visibleTo($me)
            ->when($term !== '' && $number === null, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('computer', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('inventory_number', 'like', "%$term%"))
                ->orWhereHas('fromUser', fn ($u) => $u->where('name', 'like', "%$term%"))
                ->orWhereHas('toUser', fn ($u) => $u->where('name', 'like', "%$term%"))
                ->orWhere('to_department', 'like', "%$term%")))
            ->orderByRaw("FIELD(status, 'pending', 'received') = 0") // нээлттэй нь эхэнд
            ->latest()->orderByDesc('id');

        $transfers = \App\Support\RowNumber::paginate($query, $request, 20, $number);

        return view('transfers.index', [
            'showBranch' => $me->isSuperadmin(),
            'transfers' => $transfers,
            'employees' => User::query()->assignableBy($me)->where('role', 'employee')->orderBy('name')->get(['id', 'name', 'username', 'branch_id']),
            // Супер админд: шилжүүлэх чиглэл, админууд, алба хэлтсүүд (төхөөрөмж, хэрэглэгчдийн бүртгэлээс)
            'destinations' => $me->isSuperadmin() ? self::DESTINATIONS : [],
            'admins' => $me->isSuperadmin()
                ? User::query()->assignableBy($me)->where('role', 'admin')->with('branch:id,name')->orderBy('name')->get(['id', 'name', 'username', 'branch_id'])
                : collect(),
            'departments' => $me->isSuperadmin()
                ? Computer::query()->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department')
                    ->merge(User::query()->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department'))
                    ->unique()->sort()->values()
                : collect(),
            // Шилжүүлж болох төхөөрөмж: харах эрхтэй салбарын, засварт/актласан биш;
            // Админд зөвхөн ажилтанд байгаа, Супер админд хэлтэст, агуулахад байгаа нь ч
            'devices' => Computer::query()->with(['assignedUser:id,name', 'branch:id,name'])
                ->when(! $me->isSuperadmin(), fn ($q) => $q->whereNotNull('assigned_user_id'))
                ->whereNotIn('status', [ComputerStatus::InRepair, ComputerStatus::Decommissioned])
                ->orderBy('assigned_to')->orderBy('inventory_number')
                ->get(['id', 'inventory_number', 'name', 'category', 'status', 'department', 'location', 'assigned_user_id', 'assigned_to', 'kit_parent_id', 'kit_index', 'branch_id'])
                ->each(fn ($d) => $d->setAttribute('place', $this->placeLabel($d))),
        ]);
    }

    /** 2) IT хүлээн авах: хариуцагчийг цуцалж, агуулахад оруулна */
    public function receive(Request $request, ComputerTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $transfer);
        abort_unless($transfer->status === 'pending', 422);

        $computer = $transfer->computer;
        abort_unless($computer && ! $computer->trashed(), 422);

        DB::transaction(function () use ($transfer, $computer, $request) {
            $previous = $computer->assigned_user_id;

            $computer->assigned_user_id = null;
            $computer->assigned_to = null;
            $computer->status = ComputerStatus::InStorage;
            $computer->save();

            $this->log($computer->id, $request, 'returned', $previous,
                'Шилжүүлэх хүсэлтээр IT-д хүлээлгэн өгсөн'.($transfer->reason ? ': '.$transfer->reason : ''));

            $transfer->update([
                'status' => 'received',
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);
        });

        return back()->with('success', $computer->activityLabel().' IT-д хүлээн авч, агуулахад орлоо. Одоо дараагийн ажилтанд олгоно уу.');
    }

    /** 3) Дараагийн ажилтанд олгох (IT-д хүлээн авсан төхөөрөмж) */
    public function issue(Request $request, ComputerTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $transfer);
        abort_unless($transfer->status === 'received', 422);

        $data = $request->validate([
            'user_id' => ['required', 'integer', User::assignableRule($request->user())],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'user_id.required' => 'Олгох ажилтнаа сонгоно уу.',
            'user_id.exists' => 'Сонгосон хэрэглэгч олдсонгүй.',
        ]);

        $computer = $transfer->computer;
        abort_unless($computer && ! $computer->trashed(), 422);
        $next = User::findOrFail($data['user_id']);

        DB::transaction(function () use ($transfer, $computer, $next, $request, $data) {
            $computer->assigned_user_id = $next->id; // model нь assigned_to-г синк хийж, баталгааг арилгана
            $computer->status = ComputerStatus::Active;
            $computer->save();

            $this->log($computer->id, $request, 'assigned', $next->id,
                trim('IT-аар дамжуулан олгосон (өмнөх: '.($transfer->fromUser?->name ?? '—').'). '.($data['note'] ?? '')));

            $transfer->update([
                'status' => 'completed',
                'issued_to_user_id' => $next->id,
                'issued_by' => $request->user()->id,
                'completed_at' => now(),
            ]);
        });

        return back()->with('success', $computer->activityLabel().' → '.$next->name.'-д олголоо.');
    }

    public function reject(Request $request, ComputerTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfer($request, $transfer);
        abort_unless($transfer->status === 'pending', 422);

        $transfer->update([
            'status' => 'rejected',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Хүсэлт татгалзагдлаа.');
    }

    /** Админ зөвхөн өөрийн салбарын шилжүүлгийг шийдвэрлэнэ */
    private function authorizeTransfer(Request $request, ComputerTransfer $transfer): void
    {
        abort_unless(ComputerTransfer::visibleTo($request->user())->whereKey($transfer->id)->exists(), 403);
    }

    private function log(int $computerId, Request $request, string $type, ?int $userId, ?string $note): void
    {
        ComputerEvent::create([
            'computer_id' => $computerId,
            'actor_id' => $request->user()->id,
            'user_id' => $userId,
            'type' => $type,
            'note' => filled($note) ? trim($note) : null,
        ]);
    }
}
