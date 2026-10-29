<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Computer;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Алба хэлтсийн бүртгэл: Супер админ бүх салбарын, Админ өөрийн салбарын хэлтсийг нэмж, нэр солих, нэгтгэх,
 * устгах (төхөөрөмж, хэрэглэгчгүй бол). Нэр солиход холбогдсон төхөөрөмж, хэрэглэгч дээр шууд солигдоно.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();

        $departments = Department::query()
            ->with('branch:id,name')
            ->when($me->isBranchScoped(), fn ($q) => $q->where('branch_id', $me->branch_id))
            ->when($request->filled('branch') && $me->isSuperadmin(), fn ($q) => $q->where('branch_id', (int) $request->query('branch')))
            ->withCount(['computers' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->counted(), 'users'])
            ->orderBy('branch_id')->orderBy('name')->get();

        return view('departments.index', [
            'departments' => $departments,
            'branches' => $me->isSuperadmin() ? Branch::query()->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$branchId, $name] = $this->validated($request);
        $department = Department::create(['branch_id' => $branchId, 'name' => $name]);

        return back()->with('success', '«'.$department->name.'» алба нэмэгдлээ.')->with('success_autohide', 3000);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeDepartment($request, $department);
        [, $name] = $this->validated($request, $department);
        $department->update(['name' => $name]); // төхөөрөмж, хэрэглэгч дээрх нэр хамт солигдоно (Department::booted)

        return back()->with('success', 'Алба «'.$department->name.'» болж солигдлоо.')->with('success_autohide', 3000);
    }

    /** Нэгтгэх: энэ хэлтсийн төхөөрөмж, хэрэглэгчийг өөр хэлтэс рүү шилжүүлж, энэ хэлтсийг устгана (үсгийн алдаатай давхардал) */
    public function merge(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeDepartment($request, $department);
        $target = Department::query()->whereKey((int) $request->input('target_id'))
            ->where('branch_id', $department->branch_id)->whereKeyNot($department->id)->first();
        if (! $target) {
            throw ValidationException::withMessages(['target_id' => 'Нэгтгэх албаа (ижил салбарын) сонгоно уу.']);
        }

        DB::transaction(function () use ($department, $target) {
            $moved = [];
            foreach (['computers', 'users'] as $table) {
                $moved[$table] = DB::table($table)->where('department_id', $department->id)
                    ->update(['department_id' => $target->id, 'department' => $target->name]);
            }
            // Түүхэнд «Нэгтгэсэн» (хаашаа, хэдэн төхөөрөмж, ажилтан); тусдаа «Устгасан» бичихгүй
            $department->recordActivity('merged', [
                'merged_into' => ['new' => $target->name],
                'moved_devices' => ['new' => $moved['computers']],
                'moved_users' => ['new' => $moved['users']],
            ]);
            $department->deleteQuietly();
        });

        return back()->with('success', '«'.$department->name.'» → «'.$target->name.'» руу нэгтгэгдлээ.')->with('success_autohide', 3000);
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeDepartment($request, $department);
        $devices = Computer::withoutGlobalScopes()->where('department_id', $department->id)->count();
        $users = User::query()->where('department_id', $department->id)->count();
        if ($devices || $users) {
            return back()->withErrors(['department' => '«'.$department->name.'» албанд '.$devices.' төхөөрөмж, '.$users.' хэрэглэгч байгаа тул устгах боломжгүй. Өөр алба руу нэгтгэнэ үү.']);
        }

        $department->delete();

        return back()->with('success', '«'.$department->name.'» алба устгагдлаа.')->with('success_autohide', 3000);
    }

    /** Админ зөвхөн өөрийн салбарын хэлтэс */
    private function authorizeDepartment(Request $request, Department $department): void
    {
        $me = $request->user();
        abort_if($me->isBranchScoped() && (int) $department->branch_id !== (int) $me->branch_id, 403);
    }

    /** @return array{0: ?int, 1: string} [салбар, цэвэрлэсэн нэр]; салбар дотор нэр давтагдахгүй (том/жижиг үсэг ялгахгүй) */
    private function validated(Request $request, ?Department $department = null): array
    {
        $me = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => [$department || $me->isBranchScoped() ? 'nullable' : 'required', 'integer', 'exists:branches,id'],
        ], [
            'name.required' => 'Албаны нэрийг оруулна уу.',
            'branch_id.required' => 'Салбараа сонгоно уу.',
        ]);

        $branchId = $department?->branch_id ?? ($me->isBranchScoped() ? $me->branch_id : (int) $data['branch_id']);
        $name = Department::cleanName($data['name']);
        $taken = Department::query()->where('branch_id', $branchId)->when($department, fn ($q) => $q->whereKeyNot($department->id))
            ->get()->contains(fn ($d) => mb_strtolower($d->name) === mb_strtolower($name));
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Энэ салбарт «'.$name.'» алба бүртгэлтэй байна.']);
        }

        return [$branchId, $name];
    }
}
