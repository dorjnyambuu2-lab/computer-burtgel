<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Салбарын бүртгэл (зөвхөн Супер админ): салбар бүрт хариуцах Админ, ажилтан, төхөөрөмж */
class BranchController extends Controller
{
    public function index(): View
    {
        return view('branches.index', [
            'branches' => Branch::query()
                ->withCount(['computers', 'users as employees_count' => fn ($q) => $q->where('role', 'employee')])
                ->with(['admins' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = Branch::create($this->validated($request));

        return back()->with('success', '«'.$branch->name.'» салбар нэмэгдлээ. Хэрэглэгч хуудаснаас админ оноож болно.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $branch->update($this->validated($request, $branch));

        // Хэрэглэгчийн формоос салбарын нэрийг шууд засахад (fetch) шинэ нэрийг буцаана
        if ($request->expectsJson()) {
            return response()->json(['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code, 'label' => $branch->label()]);
        }

        return back()->with('success', '«'.$branch->name.'» салбарын мэдээлэл шинэчлэгдлээ.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        // Төхөөрөмж, хэрэглэгчтэй салбарыг устгахгүй (эхлээд өөр салбарт шилжүүлнэ)
        $devices = $branch->computers()->withTrashed()->count();
        $users = $branch->users()->count();
        if ($devices || $users) {
            return back()->withErrors(['branch' => '«'.$branch->name.'» салбарт '.$devices.' төхөөрөмж, '.$users.' хэрэглэгч байгаа тул устгах боломжгүй. Эхлээд тэднийг өөр салбарт шилжүүлнэ үү.']);
        }

        $branch->delete();

        return back()->with('success', '«'.$branch->name.'» салбар устгагдлаа.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $request->merge(['code' => filled($request->input('code')) ? mb_strtoupper(trim($request->input('code'))) : null]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($branch?->id)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($branch?->id)],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Салбарын нэрийг оруулна уу.',
            'name.unique' => 'Ийм нэртэй салбар бүртгэлтэй байна.',
            'code.unique' => 'Ийм кодтой салбар бүртгэлтэй байна.',
        ]);
    }
}
