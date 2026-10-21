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
use Illuminate\View\View;

/**
 * Төхөөрөмж шилжүүлэх — зөвхөн салбарын Админ (Супер админ, ажилтан шилжүүлэх эрхгүй):
 * ажилтанд байгаа төхөөрөмжийг өөрийн салбарын өөр ажилтанд шилжүүлнэ. Шилжүүлэг бүр түүхэнд
 * (computer_transfers, computer_events) бичигдэнэ.
 * Өмнө ажилтнаас ирсэн шийдвэрлэгдээгүй хүсэлтийг (pending → received → completed) мөн шийдвэрлэнэ.
 */
class ComputerTransferController extends Controller
{
    /** Ажилтанд байгаа төхөөрөмжийг өөр ажилтанд шууд шилжүүлэх */
    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'computer_id' => ['required', 'integer'],
            'to_user_id' => ['required', 'integer', User::assignableRule($me)],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'computer_id.required' => 'Шилжүүлэх төхөөрөмжөө сонгоно уу.',
            'to_user_id.required' => 'Шилжүүлэн авах ажилтнаа сонгоно уу.',
            'to_user_id.exists' => 'Сонгосон ажилтан олдсонгүй эсвэл идэвхгүй байна.',
        ]);

        // Өөрийн салбарын (BranchScope), ажилтанд оноогдсон төхөөрөмж
        $computer = Computer::query()->whereKey($data['computer_id'])->whereNotNull('assigned_user_id')->first();
        if (! $computer) {
            return back()->withInput()->withErrors(['computer_id' => 'Төхөөрөмж олдсонгүй эсвэл ажилтанд оноогдоогүй байна. Оноогдоогүй төхөөрөмжийг «Төхөөрөмж олгох»-оор олгоно.']);
        }
        if (in_array($computer->status, [ComputerStatus::InRepair, ComputerStatus::Decommissioned], true)) {
            return back()->withInput()->withErrors(['computer_id' => $computer->activityLabel().' «'.$computer->status->label().'» төлөвтэй тул шилжүүлэх боломжгүй.']);
        }
        if ((int) $computer->assigned_user_id === (int) $data['to_user_id']) {
            return back()->withInput()->withErrors(['to_user_id' => 'Төхөөрөмж аль хэдийн энэ ажилтанд байна. Өөр ажилтан сонгоно уу.']);
        }
        if (ComputerTransfer::where('computer_id', $computer->id)->open()->exists()) {
            return back()->withInput()->withErrors(['computer_id' => 'Энэ төхөөрөмжид шийдвэрлэгдээгүй хүсэлт байна. Эхлээд доорх жагсаалтаас шийдвэрлэнэ үү.']);
        }

        $from = User::find($computer->assigned_user_id);
        $next = User::findOrFail($data['to_user_id']);

        DB::transaction(function () use ($computer, $from, $next, $request, $data) {
            $this->log($computer->id, $request, 'returned', $from?->id, 'Шилжүүлэхээр буцаан авсан'.(filled($data['reason'] ?? null) ? ': '.$data['reason'] : ''));

            $computer->assigned_user_id = $next->id; // model нь assigned_to-г синк хийж, «хүлээн авсан» баталгааг арилгана
            $computer->status = ComputerStatus::Active;
            $computer->save();

            $this->log($computer->id, $request, 'assigned', $next->id, trim('Шилжүүлсэн (өмнөх: '.($from?->name ?? '—').'). '.($data['reason'] ?? '')));

            ComputerTransfer::create([
                'computer_id' => $computer->id,
                'from_user_id' => $from?->id ?? $request->user()->id,
                'to_user_id' => $next->id,
                'issued_to_user_id' => $next->id,
                'issued_by' => $request->user()->id,
                'reason' => $data['reason'] ?? null,
                'status' => 'transferred',
                'completed_at' => now(),
            ]);
        });

        return back()->with('success', $computer->activityLabel().': '.($from?->name ?? '—').' → '.$next->name.'-д шилжүүллээ.');
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
                ->orWhereHas('toUser', fn ($u) => $u->where('name', 'like', "%$term%"))))
            ->orderByRaw("FIELD(status, 'pending', 'received') = 0") // нээлттэй нь эхэнд
            ->latest()->orderByDesc('id');

        $transfers = \App\Support\RowNumber::paginate($query, $request, 20, $number);

        return view('transfers.index', [
            'transfers' => $transfers,
            'employees' => User::query()->assignableBy($me)->where('role', 'employee')->orderBy('name')->get(['id', 'name', 'username']),
            // Шилжүүлж болох төхөөрөмж: өөрийн салбарын, ажилтанд байгаа, засварт/актласан биш
            'devices' => Computer::query()->with('assignedUser:id,name')
                ->whereNotNull('assigned_user_id')
                ->whereNotIn('status', [ComputerStatus::InRepair, ComputerStatus::Decommissioned])
                ->orderBy('assigned_to')->orderBy('inventory_number')
                ->get(['id', 'inventory_number', 'name', 'category', 'assigned_user_id', 'assigned_to']),
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
