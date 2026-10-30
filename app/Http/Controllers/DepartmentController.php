<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Computer;
use App\Models\Department;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Алба хэлтэс, нэгжийн бүртгэл (UnitController нь энэ классыг өвлөнө): Супер админ бүх салбарын, Админ өөрийн
 * салбарынхыг нэмж, нэр солих, нэгтгэх, устгах (төхөөрөмж, хэрэглэгчгүй бол). Нэр солиход холбогдсон төхөөрөмж,
 * хэрэглэгч дээр шууд солигдоно.
 */
class DepartmentController extends Controller
{
    /** @var class-string<Department|Unit> */
    protected const MODEL = Department::class;

    /** Мессежийн үгс: [нэр, -д, -аа, -ны] */
    protected const WORDS = ['алба', 'албанд', 'албаа', 'албаны'];

    public function index(Request $request): View
    {
        $me = $request->user();
        $list = fn (string $class) => $class::query()
            ->with('branch:id,name')
            ->when($me->isBranchScoped(), fn ($q) => $q->where('branch_id', $me->branch_id))
            ->when($request->filled('branch') && $me->isSuperadmin(), fn ($q) => $q->where('branch_id', (int) $request->query('branch')));

        return view('departments.index', [
            'departments' => $list(static::MODEL)
                ->withCount(['computers' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->counted(), 'users'])
                ->when(static::MODEL === Department::class, fn ($q) => $q->with('unit:id,name'), fn ($q) => $q->withCount('departments'))
                ->orderBy('branch_id')->orderBy('name')->get(),
            // Алба: харьяалах нэгж сонгох жагсаалт (салбар бүрийнх)
            'units' => static::MODEL === Department::class ? $list(Unit::class)->orderBy('name')->get(['id', 'branch_id', 'name']) : collect(),
            'branches' => $me->isSuperadmin() ? Branch::query()->orderBy('name')->pluck('name', 'id') : collect(),
            // Табууд: «Алба», «Нэгж» тус бүрийн тоо
            'kind' => static::MODEL::COLUMN,
            'counts' => ['department' => $list(Department::class)->count(), 'unit' => $list(Unit::class)->count()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$branchId, $name] = $this->validated($request);
        $record = static::MODEL::create(['branch_id' => $branchId, 'name' => $name] + $this->unitData($request, $branchId));

        return back()->with('success', '«'.$record->name.'» '.static::WORDS[0].' нэмэгдлээ.')->with('success_autohide', 3000);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $record = $this->find($request, $id);

        // Албыг нэгжид оноох / нэгжээс гаргах (нэр өөрчлөхгүй)
        if (! $request->has('name') && $request->has('unit_id') && $record instanceof Department) {
            $old = $record->unit?->name;
            $record->fill($this->unitData($request, $record->branch_id));
            if (! $record->isDirty('unit_id')) {
                return back();
            }
            // Түүхэнд unit_id биш нэгжийн нэрээр: «Нэгж: МЧ → Захиргаа»
            $record->saveQuietly();
            $unit = $record->fresh()->unit;
            $record->recordActivity('updated', ['unit' => ['old' => $old, 'new' => $unit?->name]]);

            return back()->with('success', '«'.$record->name.'» '.($unit ? '«'.$unit->name.'» нэгжид харьяалагдлаа.' : 'нэгжгүй боллоо.'))
                ->with('success_autohide', 3000);
        }

        [, $name] = $this->validated($request, $record);
        $record->update(['name' => $name]); // төхөөрөмж, хэрэглэгч дээрх нэр хамт солигдоно (BranchList)

        return back()->with('success', mb_convert_case(static::WORDS[0], MB_CASE_TITLE).' «'.$record->name.'» болж солигдлоо.')->with('success_autohide', 3000);
    }

    /** Нэгтгэх: энэ бичлэгийн төхөөрөмж, хэрэглэгчийг өөр рүү шилжүүлж, энэ бичлэгийг устгана (үсгийн алдаатай давхардал) */
    public function merge(Request $request, string $id): RedirectResponse
    {
        $record = $this->find($request, $id);
        $target = static::MODEL::query()->whereKey((int) $request->input('target_id'))
            ->where('branch_id', $record->branch_id)->whereKeyNot($record->id)->first();
        if (! $target) {
            throw ValidationException::withMessages(['target_id' => 'Нэгтгэх '.static::WORDS[2].' (ижил салбарын) сонгоно уу.']);
        }

        $column = static::MODEL::COLUMN;
        DB::transaction(function () use ($record, $target, $column) {
            $moved = [];
            foreach (['computers', 'users'] as $table) {
                $moved[$table] = DB::table($table)->where($column.'_id', $record->id)
                    ->update([$column.'_id' => $target->id, $column => $target->name]);
            }
            // Нэгж нэгтгэхэд түүний албууд шинэ нэгжид
            if ($record instanceof Unit) {
                Department::query()->where('unit_id', $record->id)->update(['unit_id' => $target->id]);
            }
            // Түүхэнд «Нэгтгэсэн» (хаашаа, хэдэн төхөөрөмж, ажилтан); тусдаа «Устгасан» бичихгүй
            $record->recordActivity('merged', [
                'merged_into' => ['new' => $target->name],
                'moved_devices' => ['new' => $moved['computers']],
                'moved_users' => ['new' => $moved['users']],
            ]);
            $record->deleteQuietly();
        });

        return back()->with('success', '«'.$record->name.'» → «'.$target->name.'» руу нэгтгэгдлээ.')->with('success_autohide', 3000);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $record = $this->find($request, $id);
        $column = static::MODEL::COLUMN.'_id';
        $devices = Computer::withoutGlobalScopes()->where($column, $record->id)->count();
        $users = User::query()->where($column, $record->id)->count();
        if ($devices || $users) {
            return back()->withErrors([static::MODEL::COLUMN => '«'.$record->name.'» '.static::WORDS[1].' '.$devices.' төхөөрөмж, '.$users
                .' хэрэглэгч байгаа тул устгах боломжгүй. Өөр '.static::WORDS[0].' руу нэгтгэнэ үү.']);
        }

        $record->delete();

        return back()->with('success', '«'.$record->name.'» '.static::WORDS[0].' устгагдлаа.')->with('success_autohide', 3000);
    }

    /** Албын харьяалах нэгж: ижил салбарын нэгж эсвэл хоосон (нэгжгүй). Нэгжид хамааралгүй. */
    private function unitData(Request $request, ?int $branchId): array
    {
        if (static::MODEL !== Department::class || ! $request->has('unit_id')) {
            return [];
        }
        $unitId = $request->filled('unit_id') ? (int) $request->input('unit_id') : null;
        if ($unitId && ! Unit::query()->whereKey($unitId)->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'Нэгжээ (ижил салбарын) сонгоно уу.']);
        }

        return ['unit_id' => $unitId];
    }

    /** Админ зөвхөн өөрийн салбарынхыг */
    private function find(Request $request, string $id): Model
    {
        $record = static::MODEL::query()->findOrFail((int) $id);
        $me = $request->user();
        abort_if($me->isBranchScoped() && (int) $record->branch_id !== (int) $me->branch_id, 403);

        return $record;
    }

    /** @return array{0: ?int, 1: string} [салбар, цэвэрлэсэн нэр]; салбар дотор нэр давтагдахгүй (том/жижиг үсэг ялгахгүй) */
    private function validated(Request $request, ?Model $record = null): array
    {
        $me = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => [$record || $me->isBranchScoped() ? 'nullable' : 'required', 'integer', 'exists:branches,id'],
        ], [
            'name.required' => mb_convert_case(static::WORDS[3], MB_CASE_TITLE).' нэрийг оруулна уу.',
            'branch_id.required' => 'Салбараа сонгоно уу.',
        ]);

        $branchId = $record?->branch_id ?? ($me->isBranchScoped() ? $me->branch_id : (int) $data['branch_id']);
        $name = static::MODEL::cleanName($data['name']);
        $taken = static::MODEL::query()->where('branch_id', $branchId)->when($record, fn ($q) => $q->whereKeyNot($record->id))
            ->get()->contains(fn ($d) => mb_strtolower($d->name) === mb_strtolower($name));
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Энэ салбарт «'.$name.'» '.static::WORDS[0].' бүртгэлтэй байна.']);
        }

        return [$branchId, $name];
    }
}
