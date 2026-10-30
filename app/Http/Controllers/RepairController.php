<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Enums\RepairStage;
use App\Models\AssetRequest;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Support\Money;
use App\Support\RepairService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Засварын дараалал (Админ, Супер админ) */
class RepairController extends Controller
{
    public function __construct(private RepairService $repairs) {}

    public function index(Request $request): View
    {
        // Дугаар (#1, #2 ...) нь шүүлтээс үл хамааран бүхэл дараалал дахь байр
        $numbers = Computer::repairQueueNumbers();

        // «Дууссан» таб: дууссан засвар бүр (Админд өөрийн салбарынх — Computer-ийн BranchScope)
        $finishedQuery = fn () => ComputerEvent::query()->where('type', 'repaired')
            ->whereHas('computer', fn ($q) => $q->search($request->query('q')));
        $finished = $request->query('stage') === 'done'
            ? $this->finishedRepairs($finishedQuery()->with(['computer', 'actor:id,name'])->latest()->latest('id')->paginate(20)->withQueryString())
            : null;

        $computers = Computer::query()
            ->repairQueue()
            ->with('assignedUser:id,name')
            ->withSum(['events as repair_cost_sum' => fn ($q) => $q->reorder()], 'cost')
            // Эвдрэлийн тайлбар (засварт бүртгэхэд бичсэн) ба мэдээлсэн ажилтан (ажилтны хүсэлтээс ирсэн бол)
            ->addSelect(['repair_reason' => \App\Models\ComputerEvent::query()->select('note')
                ->whereColumn('computer_id', 'computers.id')->where('type', 'repair')->latest('id')->limit(1)])
            ->addSelect(['reported_by' => AssetRequest::query()->join('users', 'users.id', '=', 'asset_requests.user_id')
                ->select('users.name')->whereColumn('asset_requests.computer_id', 'computers.id')
                ->where('asset_requests.type', 'issue')->whereIn('asset_requests.status', ['pending', 'in_progress'])
                ->latest('asset_requests.id')->limit(1)])
            ->search($request->query('q'))
            ->when($request->query('stage') === 'queued', fn ($q) => $q->where(fn ($w) => $w->whereNull('repair_stage')->orWhere('repair_stage', RepairStage::Queued)))
            ->when($request->query('stage') !== 'queued' ? RepairStage::tryFrom((string) $request->query('stage')) : null,
                fn ($q, $stage) => $q->where('repair_stage', $stage))
            ->get();

        // Явцын табуудын тоо (хайлтаас үл хамааран бүх дараалал); явц оноогоогүй нь «Дараалалд»
        $stageCounts = Computer::query()->repairQueue()->reorder()->selectRaw('repair_stage, count(*) as c')->groupBy('repair_stage')
            ->pluck('c', 'repair_stage')->mapWithKeys(fn ($c, $k) => [$k === '' || $k === null ? RepairStage::Queued->value : $k => $c])->all();

        return view('repairs.index', [
            'computers' => $computers,
            'numbers' => $numbers,
            'stages' => RepairStage::options(),
            'stageCounts' => $stageCounts,
            'total' => count($numbers),
            'finished' => $finished,
            'finishedCount' => ComputerEvent::query()->where('type', 'repaired')->whereHas('computer')->count(),
        ]);
    }

    /**
     * Дууссан засвар бүрд: засварт орсон огноо, эвдрэл (тухайн засварын «repair» үйлдэл), нийт зардал
     * (орсноос дуусах хүртэлх үйлдлүүдийн зардал) нэмнэ.
     */
    private function finishedRepairs($page)
    {
        $events = ComputerEvent::query()
            ->whereIn('computer_id', $page->pluck('computer_id')->unique())
            ->whereIn('type', ['repair', 'repair_update', 'repaired'])
            ->orderBy('id')->get(['id', 'computer_id', 'type', 'note', 'cost', 'created_at'])
            ->groupBy('computer_id');

        $page->getCollection()->each(function (ComputerEvent $done) use ($events) {
            $before = ($events[$done->computer_id] ?? collect())->where('id', '<=', $done->id);
            $start = $before->where('type', 'repair')->last();
            $done->started_at = $start?->created_at;
            $done->reason = $start?->note;
            $done->total_cost = (float) $before->where('id', '>=', $start?->id ?? 0)->sum('cost');
        });

        return $page;
    }

    /** Засварын явц өөрчлөх эсвэл дуусгах */
    public function update(Request $request, Computer $computer): RedirectResponse
    {
        $request->merge(['cost' => Money::parse($request->input('cost'))]);
        $data = $request->validate([
            'stage' => ['required', Rule::in([...array_keys(RepairStage::options()), 'done'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'stage.required' => 'Засварын явцыг сонгоно уу.',
            'cost.numeric' => 'Зардлыг тоогоор оруулна уу.',
        ]);
        $cost = $data['cost'] ?? null;

        if ($computer->status !== ComputerStatus::InRepair) {
            return back()->withErrors(['stage' => 'Энэ компьютер засварт байхгүй байна.']);
        }

        if ($data['stage'] === 'done') {
            $this->repairs->complete($computer, $request->user(), $data['note'] ?? null, $cost);

            return back()->with('success', $computer->activityLabel().': засвар дууслаа, «'.$computer->status->label().'» төлөвт шилжлээ.')->with('success_autohide', 3000);
        }

        $this->repairs->updateStage($computer, $request->user(), RepairStage::from($data['stage']), $data['note'] ?? null, $cost);

        return back()->with('success', $computer->activityLabel().': '.$computer->repair_stage->label().'.')->with('success_autohide', 3000);
    }

    public function move(Request $request, Computer $computer): RedirectResponse
    {
        $request->validate(['direction' => ['required', 'in:up,down']]);

        $moved = $this->repairs->move($computer, $request->input('direction') === 'up' ? -1 : 1);

        return back()->with($moved ? 'success' : 'warning', $moved
            ? $computer->activityLabel().' дараалалд #'.$computer->repairQueueNumber().' боллоо.'
            : 'Цааш шилжүүлэх боломжгүй.')->with('success_autohide', 3000);
    }

    /** Ажилтны эвдрэлийн хүсэлтээс компьютерийг засварын дараалалд оруулна */
    public function fromRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        // Зөвхөн харах эрхтэй (Админд өөрийн салбарын) хүсэлт
        abort_unless(AssetRequest::visibleTo($request->user())->whereKey($assetRequest->id)->exists(), 403);
        $computer = $assetRequest->computer;

        if ($assetRequest->type !== 'issue' || ! $computer) {
            return back()->withErrors(['request' => 'Энэ хүсэлтэд компьютер сонгоогүй тул дараалалд оруулах боломжгүй.']);
        }
        if ($computer->status === ComputerStatus::Decommissioned) {
            return back()->withErrors(['request' => 'Актласан төхөөрөмжийг засварт оруулах боломжгүй.']);
        }

        if ($computer->status !== ComputerStatus::InRepair) {
            $note = trim(($assetRequest->subject ? $assetRequest->subject.': ' : '').$assetRequest->description);
            $this->repairs->start($computer, $request->user(), $note);
        }

        $assetRequest->update(['status' => 'in_progress', 'handled_by' => $request->user()->id]);

        // Ажилтны хүсэлт → Засвар: дараалалд орсон төхөөрөмжийг Засвар хуудаснаас үргэлжлүүлнэ
        return redirect()->route('repairs.index')
            ->with('success', $computer->activityLabel().' засварын дараалалд #'.$computer->repairQueueNumber().' орлоо.')->with('success_autohide', 3000);
    }
}
