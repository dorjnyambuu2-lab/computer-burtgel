<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Enums\EquipmentCategory;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Models\ComputerTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
    /** Сонгох цонхонд нэг удаад буцаах иж бүрдлийн дээд тоо */
    private const DEVICE_LIMIT = 50;

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
            ->orderByRaw("CASE WHEN status IN ('pending', 'received') THEN 0 ELSE 1 END") // нээлттэй нь эхэнд (MySQL, SQLite аль алинд)
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
            // Төхөөрөмжийн жагсаалтыг хуудсанд ачаалахгүй — сонгох цонх нээгдэхэд devices()-ээс хайлтаар авна
            'hasDevices' => $this->transferableDevices($me)->exists(),
        ]);
    }

    /**
     * Шилжүүлж болох төхөөрөмж: харах эрхтэй салбарын, засварт/актласан биш;
     * Админд зөвхөн ажилтанд байгаа, Супер админд хэлтэст, агуулахад байгаа нь ч.
     */
    private function transferableDevices(User $me)
    {
        return Computer::query()
            ->when(! $me->isSuperadmin(), fn ($q) => $q->whereNotNull('assigned_user_id'))
            ->whereNotIn('status', [ComputerStatus::InRepair, ComputerStatus::Decommissioned]);
    }

    /**
     * Төхөөрөмж сонгох цонхны өгөгдөл (JSON). Иж бүрдэл (үндсэн + дагалдах, ижил эзэмшигч) нэг мөр.
     * Хайлт, шүүлтүүрийг сервер талд хийж, хамгийн ихдээ DEVICE_LIMIT мөр буцаана — өмнө нь бүх төхөөрөмжийг
     * хуудсанд ачаалдаг байсан тул төхөөрөмж олширох тусам хуудас удааширдаг байв.
     * ?id= — тухайн төхөөрөмжийн иж бүрдэл (урьдчилан сонгох), ?options=1 — шүүлтүүрийн утгууд.
     */
    public function devices(Request $request): JsonResponse
    {
        $me = $request->user();
        $str = fn (string $key) => trim((string) $request->query($key));
        $q = $str('q');
        $kit = $str('kit');

        // Иж бүрдэл бүрийн хэмжээ: «Зөвхөн иж бүрдэл / Ганц төхөөрөмж» шүүлтүүр, нийт тоонд
        $sizes = $this->transferableDevices($me)
            ->selectRaw('COALESCE(kit_parent_id, id) as root, assigned_user_id as holder, COUNT(*) as n')
            ->groupByRaw('COALESCE(kit_parent_id, id), assigned_user_id')
            ->get()->mapWithKeys(fn ($r) => [$r->root.'|'.$r->holder => (int) $r->n]);

        // Хайлтад тохирох ангилал (монгол нэрээр нь: «дэлгэц» → monitor)
        $categories = $q === '' ? [] : collect(EquipmentCategory::cases())
            ->filter(fn ($c) => mb_stripos($c->label(), $q) !== false)->map(fn ($c) => $c->value)->values()->all();

        $matches = $this->transferableDevices($me)
            ->when((int) $request->query('id') > 0, fn ($w) => $w->whereKey((int) $request->query('id')))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                ->where('inventory_number', 'like', "%$q%")->orWhere('name', 'like', "%$q%")
                ->orWhere('assigned_to', 'like', "%$q%")->orWhere('department', 'like', "%$q%")->orWhere('location', 'like', "%$q%")
                ->orWhereHas('assignedUser', fn ($u) => $u->where('name', 'like', "%$q%"))
                ->when($categories, fn ($c) => $c->orWhereIn('category', $categories))))
            ->when($str('department') !== '', fn ($w) => $w->where('department', $str('department')))
            ->when($str('location') !== '', fn ($w) => $w->where('location', $str('location')))
            ->when($me->isSuperadmin() && ctype_digit($str('branch')), fn ($w) => $w->where('branch_id', (int) $str('branch')))
            ->orderBy('assigned_to')->orderBy('inventory_number')
            ->get(['id', 'kit_parent_id', 'assigned_user_id']);

        // Тохирсон төхөөрөмжүүдийн иж бүрдлүүд (дагалдах хэрэгслээр хайсан ч бүхэл иж бүрдэл гарна)
        $keys = $matches->map(fn ($c) => ($c->kit_parent_id ?: $c->id).'|'.$c->assigned_user_id)->unique()
            ->filter(fn ($k) => $kit === '' || ($kit === 'kit') === (($sizes[$k] ?? 1) > 1))->values();
        $page = $keys->take(self::DEVICE_LIMIT);

        $roots = $page->map(fn ($k) => (int) explode('|', $k)[0])->unique()->values();
        $sets = $roots->isEmpty() ? collect() : $this->transferableDevices($me)
            ->with(['assignedUser:id,name', 'branch:id,name'])
            ->where(fn ($w) => $w->whereIn('id', $roots)->orWhereIn('kit_parent_id', $roots))
            ->get(['id', 'inventory_number', 'name', 'category', 'status', 'department', 'location', 'assigned_user_id', 'assigned_to', 'kit_parent_id', 'kit_index', 'branch_id'])
            ->each(fn ($d) => $d->setAttribute('place', $this->placeLabel($d)))
            ->groupBy(fn ($d) => ($d->kit_parent_id ?: $d->id).'|'.$d->assigned_user_id);

        $rows = $page->map(fn ($k) => isset($sets[$k]) ? $this->deviceRow($sets[$k]) : null)->filter()->values();

        $options = null;
        if ($request->boolean('options')) {
            $distinct = fn (string $col) => $this->transferableDevices($me)->whereNotNull($col)->where($col, '!=', '')
                ->distinct()->orderBy($col)->pluck($col)->values();
            $options = [
                'departments' => $distinct('department'),
                'locations' => $distinct('location'),
                'branches' => $me->isSuperadmin()
                    ? Branch::query()->whereIn('id', $this->transferableDevices($me)->select('branch_id'))->orderBy('name')->get(['id', 'name'])
                    : [],
            ];
        }

        return response()->json([
            'rows' => $rows, 'total' => $keys->count(), 'all' => $sizes->count(), 'limit' => self::DEVICE_LIMIT, 'options' => $options,
        ]);
    }

    /** Иж бүрдлийн нэг мөр: үндсэн төхөөрөмжөөр, гишүүдийн жагсаалттай */
    private function deviceRow(\Illuminate\Support\Collection $set): array
    {
        $set = $set->sortBy(fn ($d) => [$d->kit_parent_id ? 1 : 0, $d->kit_index ?? 0, $d->id])->values();
        $d = $set->first();

        return [
            'id' => $d->id,
            'ids' => $set->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'code' => $d->inventory_number,
            'name' => $d->name,
            'category' => $d->category?->label(),
            'place' => $d->place,
            'department' => $d->department,
            'location' => $d->location,
            'holder' => $d->assigned_user_id ? (string) $d->assigned_user_id : '',
            'branch' => $d->branch_id ? (string) $d->branch_id : '',
            'branchName' => $d->branch?->name,
            'kit' => $set->count(),
            'items' => $set->count() > 1 ? $set->map(fn ($i) => $i->inventory_number.' '.$i->name)->implode(', ') : '',
        ];
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

        // Засварт, актласан бол олгохгүй; дараагийн ажилтан төхөөрөмжийн салбарынх байна
        if (in_array($computer->status, [ComputerStatus::InRepair, ComputerStatus::Decommissioned], true)) {
            return back()->withErrors(['user_id' => $computer->activityLabel().' «'.$computer->status->label().'» төлөвтэй тул олгох боломжгүй.']);
        }
        if ($computer->branch_id && $next->branch_id && (int) $computer->branch_id !== (int) $next->branch_id) {
            return back()->withErrors(['user_id' => $next->name.' нь төхөөрөмжийн салбарт ('.($computer->branch?->name ?? '—').') харьяалагдахгүй байна.']);
        }

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

        return back()->with('success', $computer->activityLabel().' → '.$next->name.'-д олголоо.')->with('success_autohide', 3000);
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
