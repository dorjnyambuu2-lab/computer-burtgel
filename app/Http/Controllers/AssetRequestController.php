<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Models\AssetRequest;
use App\Models\Computer;
use App\Models\AssetRequestItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetRequestController extends Controller
{
    public const STATUSES = [
        'pending'     => 'Хүлээгдэж буй',
        'in_progress' => 'Хийгдэж буй',
        'done'        => 'Дууссан',
        'rejected'    => 'Татгалзсан',
    ];

    /** Төхөөрөмж захиалгын төлвийн нэр (хадгалах утга ижил, утга нь өөр) */
    public const REQUEST_STATUSES = [
        'pending'     => 'Хүлээгдэж буй',
        'in_progress' => 'Зөвшөөрсөн',
        'done'        => 'Хүлээлгэн өгсөн',
        'rejected'    => 'Татгалзсан',
    ];

    /** Хүсэлтийн төрлөөс хамаарах төлвийн нэрс: эвдрэл → STATUSES, захиалга → REQUEST_STATUSES */
    public static function statusesFor(?string $type): array
    {
        return $type === 'request' ? self::REQUEST_STATUSES : self::STATUSES;
    }

    /** Нэвтэрсэн ажилтанд хариуцуулсан компьютерүүд (хэрэглэгчийн ID-аар, нэр давхцахаас хамаарахгүй) */
    private function mine(Request $request)
    {
        return Computer::query()->where('assigned_user_id', $request->user()->id);
    }

    // ---------- Ажилтан ----------

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'        => ['required', 'in:issue,request'],
            'computer_id' => ['nullable', 'integer'],
            // Захиалга: олон төхөөрөмж (items[][name, quantity]); хуучин хэлбэр — ганц «Дэлгэц ×2» гарчиг
            'items'            => ['nullable', 'array', 'max:10'],
            'items.*.name'     => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'subject'     => ['nullable', Rule::requiredIf(fn () => $request->input('type') === 'request' && empty($request->input('items'))), 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
        ], [
            'subject.required'     => 'Ямар төхөөрөмж хэрэгтэйг сонгоно уу.',
            'items.*.name.required' => 'Төхөөрөмжийн нэрийг бичнэ үү.',
            'items.max'            => 'Нэг захиалгад 10 хүртэл төрөл.',
            'description.required' => 'Тайлбараа бичнэ үү.',
        ]);

        $items = [];
        // Захиалга тодорхой төхөөрөмжид хамаарахгүй; мөрүүд ба гарчиг («Процессор, UPS ×2»)
        if ($data['type'] === 'request') {
            $data['computer_id'] = null;
            $items = collect($data['items'] ?? [])->map(fn ($i) => ['name' => trim($i['name']), 'quantity' => (int) ($i['quantity'] ?? 1)])
                ->filter(fn ($i) => $i['name'] !== '')->values()->all();
            if (! $items) {
                preg_match('/^(.*?)(?:\s*×(\d+))?$/u', trim((string) $data['subject']), $m);
                $items = [['name' => mb_substr($m[1] ?: $data['subject'], 0, 120), 'quantity' => max(1, (int) ($m[2] ?? 1))]];
            }
            $data['subject'] = AssetRequest::itemsSubject($items);
        }
        unset($data['items']);

        if (! empty($data['computer_id'])) {
            $computer = $this->mine($request)->find($data['computer_id']) ?? abort(403);

            // Засварт байгаа эсвэл шийдэгдээгүй эвдрлийн хүсэлттэй төхөөрөмжийг дахин мэдээлэхгүй
            $error = match (true) {
                $computer->status === ComputerStatus::InRepair => 'Энэ төхөөрөмж аль хэдийн засварт байна.',
                AssetRequest::where('computer_id', $computer->id)->where('type', 'issue')
                    ->whereIn('status', ['pending', 'in_progress'])->exists() => 'Энэ төхөөрөмжийн эвдрлийг аль хэдийн мэдээлсэн, хариу хүлээгдэж байна.',
                default => null,
            };
            if ($error) {
                return back()->withInput()->withErrors(['computer_id' => $error]);
            }
        }

        $assetRequest = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $items, $request) {
            $assetRequest = AssetRequest::create($data + ['user_id' => $request->user()->id]);
            $assetRequest->items()->createMany($items);

            return $assetRequest;
        });
        // Салбарын Админуудад мэдэгдэл
        \App\Notifications\EmployeeRequestSubmitted::forAssetRequest($assetRequest);

        return back()->with('success', $data['type'] === 'issue'
            ? 'Эвдрэлийн мэдээлэл илгээгдлээ.'
            : 'Төхөөрөмжийн хүсэлт илгээгдлээ.')->with('success_autohide', 3000);
    }

    public function acknowledge(Request $request)
    {
        $this->mine($request)->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);
        // Акт баталгаажуулж буй компьютерийн IP, MAC-г автоматаар бүртгэнэ
        rescue(fn () => \App\Support\ClientNetwork::syncForEmployee($request->user(), $request), null, report: true);

        return back()->with('success', 'Акт баталгаажлаа. Баярлалаа!')->with('success_autohide', 3000);
    }

    // ---------- Superadmin + Админ ----------

    public function index(Request $request)
    {
        abort_unless($request->user()->canManageComputers(), 403);

        // Хайлт: «#12» бол дэс дугаараар, бусад үед ажилтан, гарчиг, тайлбараар
        $term = trim((string) $request->query('q'));
        $number = \App\Support\RowNumber::parse($term);

        // Шүүлтүүр (төлөвөөс бусад): төлөвийн табуудын тоонд ч ашиглана
        $filtered = fn () => AssetRequest::query()
            ->visibleTo($request->user())
            ->when(in_array($request->type, ['issue', 'request'], true), fn ($q) => $q->where('type', $request->type))
            ->when($term !== '' && $number === null, fn ($q) => $q->where(fn ($w) => $w
                ->where('subject', 'like', "%$term%")->orWhere('description', 'like', "%$term%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$term%"))
                ->orWhereHas('computer', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('inventory_number', 'like', "%$term%"))));

        $query = $filtered()->with(['user', 'computer', 'handler:id,name', 'items'])
            ->when(array_key_exists((string) $request->status, self::STATUSES), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("status = 'pending' desc")
            ->latest()->orderByDesc('id');

        $requests = \App\Support\RowNumber::paginate($query, $request, 15, $number);
        $statusCounts = $filtered()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all();

        return view('requests.index', compact('requests', 'statusCounts'));
    }

    /** Хүсэлтийн төлөв өөрчилж, ажилтанд хариу бичих (PATCH /requests/{id}) */
    public function update(Request $request, AssetRequest $assetRequest)
    {
        abort_unless($request->user()->canManageComputers(), 403);
        abort_unless(AssetRequest::visibleTo($request->user())->whereKey($assetRequest->id)->exists(), 403);

        // Олон мөртэй захиалга: мөр бүрийн төлөв, хариу; ерөнхий төлөв мөрүүдээс
        if ($assetRequest->type === 'request' && $assetRequest->items()->exists()) {
            return $this->updateItems($request, $assetRequest);
        }

        $data = $request->validate([
            'status'     => ['required', 'in:' . implode(',', array_keys(self::STATUSES))],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $assetRequest->status = $data['status'];
        $assetRequest->admin_note = $data['admin_note'] ?? null;
        $assetRequest->handled_by = $request->user()->id;
        $assetRequest->save();

        return back()->with('success', 'Хүсэлтийн төлөв шинэчлэгдлээ.')->with('success_autohide', 3000);
    }

    /** Захиалгын мөр бүрийг зөвшөөрөх / татгалзах / хүлээлгэн өгөх («approve_all» — хүлээгдэж буй бүгдийг зөвшөөрнө) */
    private function updateItems(Request $request, AssetRequest $assetRequest)
    {
        $data = $request->validate([
            'items'          => ['nullable', 'array'],
            'items.*.status' => ['required', Rule::in(array_keys(AssetRequestItem::STATUSES))],
            'items.*.note'   => ['nullable', 'string', 'max:500'],
            'admin_note'     => ['nullable', 'string', 'max:500'],
            'approve_all'    => ['nullable', 'boolean'],
        ], ['items.*.status.in' => 'Төлөв буруу байна.']);

        $me = $request->user();
        $changed = [];
        \Illuminate\Support\Facades\DB::transaction(function () use ($assetRequest, $data, $request, $me, &$changed) {
            foreach ($assetRequest->items as $item) {
                $input = $request->boolean('approve_all')
                    ? ($item->status === 'pending' ? ['status' => 'approved', 'note' => $item->note] : null)
                    : ($data['items'][$item->id] ?? null);
                if (! $input) {
                    continue;
                }
                $item->fill(['status' => $input['status'], 'note' => filled($input['note'] ?? null) ? trim($input['note']) : null]);
                if ($item->isDirty('status')) {
                    $changed[] = $item;
                }
                if ($item->isDirty()) {
                    $item->handled_by = $me->id;
                    $item->save();
                }
            }

            if (array_key_exists('admin_note', $data)) {
                $assetRequest->admin_note = $data['admin_note'];
            }
            $assetRequest->handled_by = $me->id;
            $assetRequest->syncStatusFromItems();
        });

        // Ажилтанд: аль мөр ямар болсон
        if ($changed) {
            \App\Support\Notify::send([$assetRequest->user], new \App\Notifications\AssetRequestItemsDecided($assetRequest, $changed));
        }

        return back()->with('success', $changed
            ? 'Захиалга шинэчлэгдлээ: '.collect($changed)->map(fn ($i) => $i->label().' — '.$i->statusLabel())->implode(', ').'.'
            : 'Захиалга хадгалагдлаа.')->with('success_autohide', 3000);
    }
}