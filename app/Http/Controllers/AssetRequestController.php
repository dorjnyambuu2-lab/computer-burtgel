<?php

namespace App\Http\Controllers;

use App\Models\AssetRequest;
use App\Models\Computer;
use Illuminate\Http\Request;

class AssetRequestController extends Controller
{
    public const STATUSES = [
        'pending'     => 'Хүлээгдэж буй',
        'in_progress' => 'Хийгдэж буй',
        'done'        => 'Дууссан',
        'rejected'    => 'Татгалзсан',
    ];

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
            // Төхөөрөмж захиалахад юу хэрэгтэйг заавал (жишээ: «Дэлгэц ×2»)
            'subject'     => ['nullable', 'required_if:type,request', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
        ], [
            'subject.required_if'  => 'Ямар төхөөрөмж хэрэгтэйг сонгоно уу.',
            'description.required' => 'Тайлбараа бичнэ үү.',
        ]);

        if (! empty($data['computer_id']) && ! $this->mine($request)->whereKey($data['computer_id'])->exists()) {
            abort(403);
        }

        $assetRequest = AssetRequest::create($data + ['user_id' => $request->user()->id]);
        // Салбарын Админуудад мэдэгдэл
        \App\Notifications\EmployeeRequestSubmitted::forAssetRequest($assetRequest);

        return back()->with('success', $data['type'] === 'issue'
            ? 'Эвдрэлийн мэдээлэл илгээгдлээ.'
            : 'Төхөөрөмжийн хүсэлт илгээгдлээ.');
    }

    public function acknowledge(Request $request)
    {
        $this->mine($request)->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);

        return back()->with('success', 'Акт баталгаажлаа. Баярлалаа!');
    }

    // ---------- Superadmin + Админ ----------

    public function index(Request $request)
    {
        abort_unless($request->user()->canManageComputers(), 403);

        // Хайлт: «#12» бол дэс дугаараар, бусад үед ажилтан, гарчиг, тайлбараар
        $term = trim((string) $request->query('q'));
        $number = \App\Support\RowNumber::parse($term);

        $query = AssetRequest::with(['user', 'computer'])
            ->visibleTo($request->user())
            ->when(array_key_exists((string) $request->status, self::STATUSES), fn ($q) => $q->where('status', $request->status))
            ->when(in_array($request->type, ['issue', 'request'], true), fn ($q) => $q->where('type', $request->type))
            ->when($term !== '' && $number === null, fn ($q) => $q->where(fn ($w) => $w
                ->where('subject', 'like', "%$term%")->orWhere('description', 'like', "%$term%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$term%"))
                ->orWhereHas('computer', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('inventory_number', 'like', "%$term%"))))
            ->orderByRaw("status = 'pending' desc")
            ->latest()->orderByDesc('id');

        $requests = \App\Support\RowNumber::paginate($query, $request, 15, $number);

        return view('requests.index', compact('requests'));
    }

    /** Хүсэлтийн төлөв өөрчилж, ажилтанд хариу бичих (PATCH /requests/{id}) */
    public function update(Request $request, AssetRequest $assetRequest)
    {
        abort_unless($request->user()->canManageComputers(), 403);
        abort_unless(AssetRequest::visibleTo($request->user())->whereKey($assetRequest->id)->exists(), 403);

        $data = $request->validate([
            'status'     => ['required', 'in:' . implode(',', array_keys(self::STATUSES))],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $assetRequest->status = $data['status'];
        $assetRequest->admin_note = $data['admin_note'] ?? null;
        $assetRequest->handled_by = $request->user()->id;
        $assetRequest->save();

        return back()->with('success', 'Хүсэлтийн төлөв шинэчлэгдлээ.');
    }
}