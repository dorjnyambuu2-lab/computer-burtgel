<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Enums\ComputerType;
use App\Enums\EquipmentCategory;
use App\Http\Requests\ComputerRequest;
use App\Models\Computer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComputerController extends Controller
{
    /** Эрэмбэлж болох баганууд: ?sort=<түлхүүр>&dir=asc|desc */
    public const SORTS = [
        'id' => 'id',
        'sequence_number' => 'sequence_number',
        'inventory_number' => 'inventory_number',
        'name' => 'name',
        'type' => 'type',
        'assigned_to' => 'assigned_to',
        'department' => 'department',
        'status' => 'status',
        'created_at' => 'created_at',
        'purchase_date' => 'purchase_date',
        'warranty_until' => 'warranty_until',
    ];

    /**
     * Дээд түвшнээс доош нарийсах шүүлт: ангилал → төрөл → төлөв → алба хэлтэс → байршил,
     * мөн бүртгэсэн админ, бүртгэсэн огноо (-аас / хүртэл), чөлөөт хайлт.
     */
    private function filtered(Request $request, bool $withStatus = true, bool $withKitItems = false)
    {
        $sort = self::SORTS[$request->query('sort')] ?? 'id';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $numbers = $this->globalNumbers($request);
        $term = trim((string) $request->query('q'));

        // Бүх салбарын нийтлэг дугаар нь бүртгэлийн (id) дарааллаар тул «№»-ээр эрэмбэлэх, «#12»-оор хайхыг id-аар хийнэ
        if ($numbers && $sort === 'sequence_number') {
            $sort = 'id';
        }
        $globalHit = $numbers && preg_match('/^#(\d+)$/', $term, $m) ? ($numbers->search((int) $m[1]) ?: 0) : null;

        return Computer::query()
            ->when($globalHit !== null, fn ($q) => $q->whereKey($globalHit), fn ($q) => $q->search($term))
            // Иж бүрдлийн дагалдах хэрэгсэл (процессортой хамт бүртгэсэн дэлгэц ...) нь компьютерийнхээ хуудсанд,
            // mouse, keyboard жагсаалтад харагдахгүй: зөвхөн хайлтаар, эсвэл ангиллаар (Mouse, Keyboard) шүүхэд гарна
            ->when(! $withKitItems && $term === '' && ! $request->filled('category'),
                fn ($q) => $q->whereNull('kit_parent_id')->counted())
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($withStatus && $request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            // Салбараар (Супер админ; Админ BranchScope-оор өөрийн салбарт хязгаарлагдсан)
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', (int) $request->query('branch')))
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->query('department')))
            ->when($request->filled('location'), fn ($q) => $q->where('location', $request->query('location')))
            ->when($request->query('warranty') === 'expiring', fn ($q) => $q->warrantyExpiringWithin(60))
            ->when($request->filled('created_by'), fn ($q) => $q->where('created_by', (int) $request->query('created_by')))
            ->when($this->date($request, 'date_from'), fn ($q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($this->date($request, 'date_to'), fn ($q, $d) => $q->where('created_at', '<=', $d->endOfDay()))
            ->orderBy($sort, $dir)
            ->when($sort !== 'id', fn ($q) => $q->orderBy('id', $dir));
    }

    /**
     * Дэс дугаар салбар бүрт тусдаа (#1, #2 ...) тул Супер админ бүх салбарыг хамт харахад давхцана.
     * Тэр үед бүх төхөөрөмжийг бүртгэлийн дарааллаар 1, 2, 3 ... гэж дугаарлана [id => дугаар];
     * салбараар шүүсэн эсвэл Админ бол null (салбарын дэс дугаар).
     */
    private function globalNumbers(Request $request): ?\Illuminate\Support\Collection
    {
        if (! $request->user()->isSuperadmin() || $request->filled('branch')) {
            return null;
        }

        // Иж бүрдлийн дагалдах хэрэгсэл, mouse, keyboard жагсаалтад харагдахгүй тул дугаарлахгүй (завсаргүй 1, 2, 3 ...)
        return once(fn () => Computer::query()->whereNull('kit_parent_id')->counted()->orderBy('id')->pluck('id')->flip()->map(fn ($i) => $i + 1));
    }

    private function date(Request $request, string $key): ?Carbon
    {
        try {
            return $request->filled($key) ? Carbon::createFromFormat('Y-m-d', $request->query($key)) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function departments()
    {
        return Computer::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department');
    }

    /** Байршлууд: алба хэлтэс сонгосон бол зөвхөн тухайн хэлтсийнх */
    private function locations(Request $request)
    {
        return Computer::query()
            ->whereNotNull('location')
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->query('department')))
            ->distinct()->orderBy('location')->pluck('location');
    }

    /**
     * Хариуцагчаар сонгож болох хэрэглэгчид (идэвхтэй ажилтнууд).
     * Засаж буй компьютерийн одоогийн хариуцагч идэвхгүй болсон ч жагсаалтад үлдэнэ,
     * ингэснээр хадгалахад хариуцагч санамсаргүй арилахгүй.
     */
    private function employees(?int $currentId = null)
    {
        // Зөвхөн ажилтан (Админ, Супер админ харагдахгүй)
        return User::query()
            ->where(function ($q) use ($currentId) {
                $q->where(fn ($w) => $w->assignableBy(request()->user())->where('role', 'employee'))
                  ->when($currentId, fn ($q) => $q->orWhere('id', $currentId));
            })
            ->orderBy('name')
            ->get();
    }

    /** Баталгаажсан өгөгдөл + хариуцагчийн ID болон ангиллын шалгалт */
    private function payload(ComputerRequest $request, ?int $currentHolderId = null): array
    {
        // Одоогийн хариуцагчийг хэвээр үлдээхийг үргэлж зөвшөөрнө; шинээр сонгоход Админ Супер админыг сонгож болохгүй
        $holderRule = (int) $request->input('assigned_user_id') === $currentHolderId && $currentHolderId
            ? 'exists:users,id'
            : User::assignableRule($request->user());

        $validated = $request->validated();
        // Админ төхөөрөмжийг өөр салбарт шилжүүлж чадахгүй (шинэ бүртгэлд model өөрийн салбарыг онооно)
        if ($request->user()->isBranchScoped()) {
            unset($validated['branch_id']);
        }

        return $validated + $request->validate([
            'assigned_user_id' => ['nullable', 'integer', $holderRule],
            'category' => ['nullable', Rule::enum(EquipmentCategory::class)],
        ], ['assigned_user_id.exists' => 'Сонгосон хариуцагчийг оноох боломжгүй.']);
    }

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true) ? (int) $request->query('per_page') : 15;

        return view('computers.index', [
            'computers' => $this->filtered($request)->with('branch:id,name')->withCount('kitItems')->paginate($perPage)->withQueryString(),
            'branches' => $request->user()->isSuperadmin() ? \App\Models\Branch::query()->orderBy('name')->pluck('name', 'id') : collect(),
            // Төлөвийн табуудын тоо: бусад шүүлтүүрийг тооцож, төлөвийг тооцохгүй
            'globalNumbers' => $this->globalNumbers($request),
            // Тоо: процессор, дэлгэц, printer ... (иж бүрдлийнх ч), mouse, keyboard-ыг тоолохгүй (Computer::scopeCounted)
            'statusCounts' => $this->filtered($request, withStatus: false, withKitItems: true)->counted()->reorder()
                ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all(),
            'deviceCount' => $this->filtered($request, withKitItems: true)->counted()->reorder()->count(),
            'perPage' => $perPage,
            'departments' => $this->departments(),
            // Алба хэлтэс бүрийн төхөөрөмжийн тоо (шүүлтүүрийн сонголтод «Алба (5)»)
            'departmentCounts' => Computer::query()->counted()->whereNotNull('department')
                ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', (int) $request->query('branch')))
                ->selectRaw('department, count(*) as c')->groupBy('department')->pluck('c', 'department')->all(),
            'locations' => $this->locations($request),
            'statuses' => ComputerStatus::options(),
            'types' => ComputerType::options(),
            'categories' => EquipmentCategory::options(),
            'admins' => User::query()->whereIn('role', ['superadmin', 'admin'])
                ->when($request->user()->isBranchScoped(), fn ($q) => $q->where('branch_id', $request->user()->branch_id))
                ->orderBy('name')->get(['id', 'name']),
            'queueNumbers' => Computer::repairQueueNumbers(),
        ]);
    }

    /**
     * «Хадгалаад дараагийнх»-д өмнөхөөс хуулах талбарууд: төрөл, байршил.
     * Техникийн үзүүлэлт, худалдан авалт, баталгааг хуулахгүй (төхөөрөмж бүрт шинээр бөглөнө).
     */
    private const PREFILL = ['type', 'category', 'status', 'department', 'unit', 'location'];

    /**
     * Бүртгэлийн форм. Хурдан бүртгэхийн тулд:
     *  - ?from={id} бол тухайн компьютерийн төрөл, байршлыг хуулна («Хадгалаад дараагийнх»)
     *  - үгүй бол энэ админы сүүлд бүртгэсэн компьютерийн алба хэлтэс, байршлыг санал болгоно
     *  - бүртгэлийн дугаар, нэрийг хоосон орхивол автоматаар үүснэ
     */
    public function create(Request $request): View
    {
        // Шинээр бүртгэхэд төлөв анхдагчаар «Шинэ» (өөрчилж болно)
        $computer = new Computer(['status' => ComputerStatus::New, 'type' => ComputerType::Desktop]);

        $source = $request->filled('from')
            ? Computer::find((int) $request->query('from'))
            : Computer::where('created_by', $request->user()->id)->latest('id')->first();

        if ($source) {
            $fields = $request->filled('from') ? self::PREFILL : ['department', 'unit', 'location'];
            $computer->fill($source->only($fields));
        }

        // ?kit={id}: тухайн компьютерийн иж бүрдэлд дагалдах хэрэгсэл нэмэх. Хэлтэс, байршил, хариуцагчийг нь авна.
        $kitParent = $request->filled('kit') ? Computer::find((int) $request->query('kit')) : null;
        if ($kitParent?->canBeKitMain()) {
            $computer->kit_parent_id = $kitParent->id;
            $computer->fill($kitParent->only(['department', 'unit', 'position', 'location', 'assigned_user_id', 'holder_surname', 'holder_name', 'status']));
            $computer->category = null;
            $computer->type = ComputerType::Other;
        }

        // Худалдан авсан огноо: анхдагчаар өнөөдөр (баталгаа дуусах огноо үүнээс автоматаар бодогдоно)
        $computer->purchase_date ??= today();

        // ?detected=1: «computer-burtgel.bat» скриптээр автоматаар уншсан мэдээлэл
        $detected = $request->boolean('detected') ? ComputerDetectController::normalize($request->query()) : [];
        // Холбогдсон дэлгэц, printer: формд «Хамт бүртгэх» гэж санал болгоно
        $detectedDevices = $request->boolean('detected')
            ? ComputerDetectController::normalizeDevices((array) $request->query('devices', []))
            : [];
        $computer->fill($detected);
        $duplicate = isset($detected['serial_number'])
            ? Computer::global()->where('serial_number', $detected['serial_number'])->first()
            : null;

        return view('computers.create', [
            'computer' => $computer,
            'detected' => array_keys($detected),
            'detectedDevices' => $detectedDevices,
            'duplicate' => $duplicate,
            // Угтвар бүрийн дараагийн дугаар: формд ангилал/төрөл сонгоход урьдчилан харуулна
            // Бүртгэлийн дугаарын урьдчилсан харагдац (PC-B1-A2-U3-20261022-001): угтвар бүрийн өдрийн дараагийн дугаар.
            // Салбар, хариуцагчийг формд сонгоход JS дугаарыг угсарна (A = бүртгэж буй админ)
            'nextSerial' => Computer::nextDailySerial(\App\Models\User::registrarCode($request->user()->id)),
            'inventoryDate' => now()->format('Ymd'),
            // Хариуцагчийн овог, нэрийг бичихэд бүртгэлийн дугаарын U хэсгийг тааруулах ажилтнууд (Computer::matchHolderUser-тэй ижил)
            'holderCandidates' => \App\Models\User::query()
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'employee'])
                ->when($request->user()->isBranchScoped(), fn ($q) => $q->where('branch_id', $request->user()->branch_id))
                ->get(['id', 'name', 'surname', 'branch_id', 'code'])
                ->map(fn ($u) => [
                    'name' => trim((string) $u->name),
                    'surname' => trim((string) $u->surname),
                    'branch' => $u->branch_id,
                    'code' => $u->code ? (int) substr($u->code, 1) : 0,
                ])->values(),
            'nextSequence' => Computer::peekSequenceNumber($request->user()->branch_id),
            'departments' => $this->departments(),
            'kitParents' => $this->kitParents(),
        ]);
    }

    /** Иж бүрдлийн үндсэн компьютер болж болох бүртгэлүүд (процессор, нөүтбүк, ангилалгүй) */
    private function kitParents()
    {
        return Computer::query()
            ->whereNull('kit_parent_id')
            ->where(fn ($q) => $q->whereIn('category', Computer::KIT_MAIN_CATEGORIES)->orWhereNull('category'))
            ->withMax(['kitItems as kit_last' => fn ($q) => $q->withTrashed()], 'kit_index')
            ->orderBy('inventory_number')
            ->get(['id', 'inventory_number', 'name', 'assigned_to', 'kit_index', 'kit_parent_id']);
    }

    /** Иж бүрдлийн үндсэн компьютерийн ID-г шалгана (зөвхөн шинээр бүртгэхэд) */
    private function kitParentId(Request $request): ?int
    {
        $id = $request->validate([
            'kit_parent_id' => ['nullable', 'integer', Rule::exists('computers', 'id')->whereNull('deleted_at')->whereNull('kit_parent_id')],
        ], ['kit_parent_id.exists' => 'Сонгосон иж бүрдэл олдсонгүй.'])['kit_parent_id'] ?? null;

        if ($id && ! Computer::find($id)?->canBeKitMain()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['kit_parent_id' => 'Иж бүрдлийн үндсэн төхөөрөмж нь процессор эсвэл нөүтбүк байх ёстой.']);
        }

        return $id;
    }

    public function store(ComputerRequest $request): RedirectResponse
    {
        $request->merge(['kit_parent_id' => $this->kitParentId($request)]);

        // Формд ангиллыг checkbox (categories[]) байдлаар сонгодог
        $categories = array_values(array_filter((array) $request->input('categories', [])));

        if (count($categories) > 1) {
            // JS ажиллаагүй үед ч олон ангилал сонгосон бол ангилал бүрээр нь тусдаа бүртгэнэ
            return app(ComputerBulkController::class)->storeMultiple($request);
        }

        if (count($categories) === 1) {
            $request->merge(['category' => $categories[0]]);
        }

        $created = DB::transaction(function () use ($request) {
            $computer = Computer::create($this->payload($request) + ['kit_parent_id' => $request->input('kit_parent_id')]);
            $created = [$computer];

            // Процессор бүртгэхэд дэлгэц, mouse, keyboard иж бүрдлийн дагалдах хэрэгсэл болж хамт бүртгэгдэнэ (-2, -3, -4 дугаартай)
            if ($computer->category === EquipmentCategory::Processor && ! $computer->kit_parent_id) {
                $with = array_intersect(EquipmentCategory::WITH_PROCESSOR, (array) $request->input('with_accessories', []));
                foreach ($with as $value) {
                    $category = EquipmentCategory::from($value);
                    $created[] = Computer::create($computer->only([
                        'branch_id', 'department', 'unit', 'position', 'location', 'holder_surname', 'holder_name',
                        'assigned_user_id', 'status', 'purchase_date', 'warranty_until',
                    ]) + [
                        // Нэр бичсэн бол «Нэр · Mouse», үгүй бол model «Mouse PC-...» гэж өгнө
                        'name' => $request->filled('name') ? $request->input('name').' · '.$category->label() : null,
                        'category' => $category,
                        'type' => ComputerType::Other,
                        'kit_parent_id' => $computer->id,
                    ]);
                }
            }

            // Дагалдах хэрэгсэл нэмэгдэхэд үндсэн компьютер -1 дугаар авдаг тул шинэчилж уншина
            return array_map(fn (Computer $c) => $c->refresh(), $created);
        });
        $computer = $created[0];

        \App\Notifications\ComputersRegistered::dispatch($request->user(), $created);
        // Хариуцагчийн нэр · бүртгэлийн дугаар (хариуцагчгүй бол зөвхөн дугаар); 3 секундын дараа алга болно
        $holder = trim($computer->holder_surname.' '.$computer->holder_name) ?: $computer->assigned_to;
        $message = ($holder ? $holder.' · ' : '').$computer->inventory_number.' амжилттай бүртгэгдлээ.';
        if ($computer->holderCreated) {
            $message .= ' «'.$computer->holderCreated->name.'» шинэ ажилтнаар нэмэгдлээ.';
        }

        // «Хадгалаад дараагийнхыг бүртгэх»: ижил төрлийн олон компьютерийг дараалан бүртгэхэд
        $redirect = $request->boolean('save_and_new')
            ? redirect()->route('computers.create', ['from' => $computer->id])
            : redirect()->route('computers.show', $computer);

        return $redirect->with('success', $message)->with('success_autohide', 3000);
    }

    public function show(Computer $computer): View
    {
        return view('computers.show', [
            'computer' => $computer,
            'employees' => $this->employees(),
            'events' => $computer->events()->with(['actor:id,name', 'user:id,name'])->limit(20)->get(),
        ]);
    }

    public function edit(Computer $computer): View
    {
        return view('computers.edit', [
            'computer' => $computer,
            'departments' => $this->departments(),
        ]);
    }

    public function update(ComputerRequest $request, Computer $computer): RedirectResponse
    {
        $computer->update($this->payload($request, $computer->assigned_user_id));

        return redirect()->route('computers.show', $computer)->with('success', 'Мэдээлэл амжилттай шинэчлэгдлээ.');
    }

    /** Логик устгалт: бааз дээр «устгагдсан» төлөвтэй архивлагдана, Устгасан хэсгээс сэргээж болно */
    public function destroy(Request $request, Computer $computer): RedirectResponse
    {
        $kitCount = $computer->kit_parent_id ? 0 : $computer->kitItems()->count();
        $computer->deleted_by = $request->user()->id;
        $computer->saveQuietly();
        $computer->delete(); // иж бүрдлийн хэрэгслүүд хамт архивлагдана (Computer::booted)
        \App\Notifications\ComputersRegistered::deleted($request->user(), $computer);

        return redirect()->route('computers.index')->with('success', 'Компьютер архивлагдлаа'
            .($kitCount ? ' (иж бүрдлийн '.$kitCount.' хэрэгслийн хамт)' : '').'. Устгасан хэсгээс сэргээж болно.');
    }

    public function export(Request $request): StreamedResponse
    {
        // CSV-д иж бүрдлийн дагалдах хэрэгсэл ч орно (бүрэн тооллого)
        $query = $this->filtered($request, withKitItems: true)->withSum(['events as repair_cost_sum' => fn ($q) => $q->reorder()], 'cost');
        $numbers = $this->globalNumbers($request);
        $headers = ['Дэс дугаар', 'Бүртгэлийн дугаар', 'Нэр', 'Төрөл', 'Үйлдвэрлэгч', 'Загвар', 'Серийн дугаар', 'Процессор', 'RAM (GB)',
            'Хадгалах төхөөрөмж', 'ҮС', 'IP', 'MAC', 'Үзүүлэлт', 'Алба хэлтэс', 'Байршил', 'Хариуцагч', 'Төлөв', 'Худалдан авсан',
            'Үнэ (₮)', 'Засварын зардал (₮)', 'Нийт өртөг (₮)', 'Баталгаат дуусах', 'Тэмдэглэл'];

        return response()->streamDownload(function () use ($query, $headers, $numbers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel-д кирилл зөв харагдуулах UTF-8 BOM
            fputcsv($out, $headers);
            $query->chunkById(200, function ($rows) use ($out, $numbers) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        isset($numbers[$c->kit_parent_id ?? $c->id]) ? '#'.$numbers[$c->kit_parent_id ?? $c->id] : $c->sequenceLabel(), $c->inventory_number, $c->name, $c->type->label(), $c->brand, $c->model, $c->serial_number, $c->cpu,
                        $c->ram_gb, $c->storage, $c->os, $c->ip_address, $c->mac_address, $c->specs, $c->department, $c->location,
                        $c->assigned_to, $c->status->label(), $c->purchase_date?->format('Y-m-d'),
                        $c->purchase_price !== null ? (float) $c->purchase_price : '', (float) $c->repair_cost_sum,
                        (float) $c->purchase_price + (float) $c->repair_cost_sum,
                        $c->warranty_until?->format('Y-m-d'), $c->notes,
                    ]);
                }
            });
            fclose($out);
        }, 'computers-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}