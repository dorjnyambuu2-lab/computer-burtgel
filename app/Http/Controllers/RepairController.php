<?php

namespace App\Http\Controllers;

use App\Enums\ComputerStatus;
use App\Enums\RepairStage;
use App\Models\AssetRequest;
use App\Models\Computer;
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

        $computers = Computer::query()
            ->repairQueue()
            ->with('assignedUser:id,name')
            ->withSum(['events as repair_cost_sum' => fn ($q) => $q->reorder()], 'cost')
            ->search($request->query('q'))
            ->when(RepairStage::tryFrom((string) $request->query('stage')),
                fn ($q, $stage) => $q->where('repair_stage', $stage))
            ->get();

        return view('repairs.index', [
            'computers' => $computers,
            'numbers' => $numbers,
            'stages' => RepairStage::options(),
            'total' => count($numbers),
        ]);
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

            return back()->with('success', $computer->activityLabel().': засвар дууслаа, «'.$computer->status->label().'» төлөвт шилжлээ.');
        }

        $this->repairs->updateStage($computer, $request->user(), RepairStage::from($data['stage']), $data['note'] ?? null, $cost);

        return back()->with('success', $computer->activityLabel().': '.$computer->repair_stage->label().'.');
    }

    public function move(Request $request, Computer $computer): RedirectResponse
    {
        $request->validate(['direction' => ['required', 'in:up,down']]);

        $moved = $this->repairs->move($computer, $request->input('direction') === 'up' ? -1 : 1);

        return back()->with($moved ? 'success' : 'warning', $moved
            ? $computer->activityLabel().' дараалалд #'.$computer->repairQueueNumber().' боллоо.'
            : 'Цааш шилжүүлэх боломжгүй.');
    }

    /** Ажилтны эвдрэлийн хүсэлтээс компьютерийг засварын дараалалд оруулна */
    public function fromRequest(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $computer = $assetRequest->computer;

        if ($assetRequest->type !== 'issue' || ! $computer) {
            return back()->withErrors(['request' => 'Энэ хүсэлтэд компьютер сонгоогүй тул дараалалд оруулах боломжгүй.']);
        }

        if ($computer->status !== ComputerStatus::InRepair) {
            $note = trim(($assetRequest->subject ? $assetRequest->subject.': ' : '').$assetRequest->description);
            $this->repairs->start($computer, $request->user(), $note);
        }

        $assetRequest->update(['status' => 'in_progress', 'handled_by' => $request->user()->id]);

        return back()->with('success', $computer->activityLabel().' засварын дараалалд #'.$computer->repairQueueNumber().' орлоо.');
    }
}
