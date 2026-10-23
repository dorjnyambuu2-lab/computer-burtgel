<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Computer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $number = \App\Support\RowNumber::parse($request->input('q'));

        $query = ActivityLog::with('user')
            // Админ: зөвхөн өөрийн салбарын төхөөрөмжүүдийн түүх (BranchScope-оор шүүгдсэн id-ууд)
            ->when($me->isBranchScoped(), fn ($q) => $q
                ->where('subject_type', (new Computer)->getMorphClass())
                ->whereIn('subject_id', Computer::withTrashed()->select('id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
            // «#12» бол дэс дугаараар (доор), бусад үед компьютерийн нэрээр
            ->when($request->filled('q') && $number === null, fn ($q) => $q->where('subject_label', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest('id');

        $logs = \App\Support\RowNumber::paginate($query, $request, 20, $number);

        return view('activity.index', [
            'logs' => $logs,
            'restoreStates' => $request->user()->isSuperadmin() ? $this->restoreStates($logs->getCollection()) : [],
            'users' => User::query()->when($me->isBranchScoped(), fn ($q) => $q->where('branch_id', $me->branch_id))->orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::ACTIONS,
        ]);
    }

    /**
     * Устгасан компьютер/төхөөрөмжийн түүхийн мөр бүрийн одоогийн байдал (Супер админд «Сэргээх» товч харуулахад):
     *  - 'active'   : аль хэдийн идэвхтэй (сэргээгдсэн) — холбоосоор нээнэ
     *  - 'trashed'  : «Устгасан» архивт байгаа — энгийн сэргээлт
     *  - 'snapshot' : бүрмөсөн устгагдсан, мэдээлэл нь түүхэнд байгаа — түүхээс сэргээнэ
     *  - 'lost'     : бүрмөсөн устгагдсан, сэргээх мэдээлэл алга
     *
     * @return array<int, string> [log id => байдал]
     */
    private function restoreStates($logs): array
    {
        $deletions = $logs->filter(fn ($log) => in_array($log->action, ['deleted', 'force_deleted'], true)
            && $log->subject_type === (new Computer)->getMorphClass() && $log->subject_id);

        $computers = Computer::global()->whereIn('id', $deletions->pluck('subject_id'))->get(['id', 'deleted_at'])->keyBy('id');

        return $deletions->mapWithKeys(function ($log) use ($computers) {
            $computer = $computers->get($log->subject_id);
            $state = match (true) {
                $computer && ! $computer->trashed() => 'active',
                (bool) $computer => 'trashed',
                $log->action === 'force_deleted' && $log->deletedSnapshot() !== null => 'snapshot',
                default => 'lost',
            };

            return [$log->id => $state];
        })->all();
    }

    /**
     * Бүрмөсөн устгасан компьютер/төхөөрөмжийг түүхэнд хадгалсан мэдээллээс сэргээх (зөвхөн Супер админ).
     * Ижил ID-тай сэргээгдэх тул өмнөх түүх нь дахин холбогдоно. Дэс дугаар шинээр олгогдоно.
     * Бүртгэлийн дугаар өөр төхөөрөмжид олгогдсон бол шинэ дугаар авна; серийн дугаар давхацвал хоосон үлдэнэ.
     * Анхааруулга: засварын түүх, шилжүүлэг зэрэг холбоотой бичлэгүүд бүрмөсөн устгахад хамт устсан тул сэргээгдэхгүй.
     */
    public function restore(ActivityLog $log)
    {
        if ($log->subject_type !== (new Computer)->getMorphClass()) {
            abort(404);
        }

        if ($existing = Computer::global()->find($log->subject_id)) {
            if ($existing->trashed()) {
                $existing->deleted_by = null;
                $existing->restore();

                return back()->with('success', '«'.$existing->activityLabel().'» сэргээгдлээ.');
            }

            return back()->with('warning', '«'.$existing->activityLabel().'» аль хэдийн идэвхтэй байна.');
        }

        $snapshot = $log->deletedSnapshot();
        if ($snapshot === null) {
            return back()->with('warning', 'Энэ бүртгэлийг сэргээх мэдээлэл түүхэнд олдсонгүй.');
        }

        // Одоо байгаа баганууд л (хуучин түүхэнд устсан багана байж болно)
        $columns = Schema::getColumnListing('computers');
        $data = array_intersect_key($snapshot, array_flip($columns));
        unset($data['deleted_at'], $data['deleted_by'], $data['sequence_number'], $data['updated_at']);

        $notes = [];
        foreach (['assigned_user_id', 'created_by'] as $userField) {
            if (! empty($data[$userField]) && ! User::whereKey($data[$userField])->exists()) {
                $data[$userField] = null;
            }
        }

        $computer = DB::transaction(function () use ($log, $data, &$notes) {
            $computer = new Computer;
            $computer->forceFill($data);
            $computer->id = $log->subject_id;

            if (blank($computer->name)) {
                $computer->name = $log->subject_label ?: 'Төхөөрөмж';
            }
            // Иж бүрдлийн үндсэн компьютер нь бүрмөсөн устгагдсан бол тусдаа төхөөрөмж болгож сэргээнэ
            if ($computer->kit_parent_id && ! Computer::global()->whereKey($computer->kit_parent_id)->exists()) {
                $computer->kit_parent_id = null;
                $computer->kit_index = null;
            }
            if (blank($computer->inventory_number)
                || Computer::global()->where('inventory_number', $computer->inventory_number)->exists()) {
                if (filled($computer->inventory_number)) {
                    $notes[] = 'бүртгэлийн дугаар '.$computer->inventory_number.' өөр төхөөрөмжид олгогдсон тул шинэ дугаар авлаа';
                }
                $computer->inventory_number = Computer::nextInventoryNumber($computer->created_by);
            }
            if (filled($computer->serial_number)
                && Computer::global()->where('serial_number', $computer->serial_number)->exists()) {
                $notes[] = 'серийн дугаар '.$computer->serial_number.' өөр төхөөрөмжид бүртгэлтэй тул хоосон үлдлээ';
                $computer->serial_number = null;
            }
            $computer->sequence_number = Computer::nextSequenceNumber($computer->branch_id);

            // «Нэмсэн» түүх давхар бичигдэхгүйн тулд event-гүй хадгалаад, «Түүхээс сэргээсэн» гэж тусад нь бичнэ
            $computer->saveQuietly();
            $computer->recordActivity('restored_from_log');

            return $computer;
        });

        return redirect()->route('computers.show', $computer)->with('success',
            '«'.$computer->activityLabel().'» түүхээс сэргээгдлээ ('.$computer->sequenceLabel().' · '.$computer->inventory_number.').'
            .($notes ? ' Анхаар: '.implode('; ', $notes).'.' : ''));
    }

    /**
     * Сонгосон түүхийг бүрмөсөн устгах: зөвхөн Супер админ (route-ийн role:superadmin), нууц үгээ дахин оруулна.
     * Устгасан гэдэг нь өөрөө түүхэнд үлдэнэ (хэн, хэдэн мөр устгасан).
     */
    public function destroy(Request $request)
    {
        $data = $request->validateWithBag('deleteLogs', [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'password' => ['required', 'string'],
        ], [
            'ids.required' => 'Устгах түүхээ сонгоно уу.',
            'password.required' => 'Устгахын тулд нууц үгээ оруулна уу.',
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Нууц үг буруу байна. Түүх устгагдсангүй.'], 'deleteLogs');
        }

        $count = ActivityLog::whereIn('id', $data['ids'])->where('action', '!=', 'logs_deleted')->delete();

        if ($count > 0) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'logs_deleted',
                'subject_label' => 'Үйлдлийн түүх',
                'changes' => ['deleted_count' => ['new' => $count]],
            ]);
        }

        return back()->with('success', $count.' түүх бүрмөсөн устгагдлаа.');
    }
}
