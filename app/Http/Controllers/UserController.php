<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Computer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function me(): User
    {
        $me = request()->user();
        abort_unless($me && $me->canManageUsers(), 403);

        return $me;
    }

    /**
     * Хариуцах салбар (Супер админд шаардлагагүй): байгаа салбараас сонгоно,
     * эсвэл branch_id=new бол шинэ салбарын нэр, код бичнэ (хадгалахад салбар үүснэ).
     */
    private function branchRules(Request $request): array
    {
        $request->merge(['new_branch_code' => filled($request->input('new_branch_code')) ? mb_strtoupper(trim($request->input('new_branch_code'))) : null]);
        $skip = ['exclude_if:role,superadmin'];

        return [
            'branch_id' => [...$skip, 'required', function ($attribute, $value, $fail) {
                if ($value !== 'new' && ! (ctype_digit((string) $value) && Branch::whereKey($value)->exists())) {
                    $fail('Сонгосон салбар олдсонгүй.');
                }
            }],
            'new_branch_name' => [...$skip, 'exclude_unless:branch_id,new', 'required', 'string', 'max:255', Rule::unique('branches', 'name')],
            'new_branch_code' => [...$skip, 'exclude_unless:branch_id,new', 'nullable', 'string', 'max:20', Rule::unique('branches', 'code')],
        ];
    }

    /** Баталгаажсан өгөгдлөөс салбарын ID: шинэ салбар үүсгэх эсвэл сонгосон салбар */
    private function branchFor(array $data): ?int
    {
        return match (true) {
            $data['role'] === 'superadmin' => null,
            ($data['branch_id'] ?? null) === 'new' => Branch::create(['name' => trim($data['new_branch_name']), 'code' => $data['new_branch_code'] ?? null])->id,
            default => (int) $data['branch_id'],
        };
    }

    private function messages(): array
    {
        return [
            'branch_id.required' => 'Хариуцах салбарыг сонгоно уу.',
            'new_branch_name.required' => 'Шинэ салбарын нэрийг бичнэ үү.',
            'new_branch_name.unique' => 'Ийм нэртэй салбар бүртгэлтэй байна. Жагсаалтаас сонгоно уу.',
            'new_branch_code.unique' => 'Ийм кодтой салбар бүртгэлтэй байна.',
            'name.required'      => 'Нэрээ оруулна уу.',
            'username.required'  => 'Нэвтрэх нэрээ оруулна уу.',
            'username.unique'    => 'Энэ нэвтрэх нэр бүртгэлтэй байна.',
            'email.required'     => 'Имэйлээ оруулна уу.',
            'email.email'        => 'Имэйл хаяг буруу байна.',
            'email.unique'       => 'Энэ имэйл бүртгэлтэй байна.',
            'role.required'      => 'Эрхийг сонгоно уу.',
            'role.in'            => 'Танд энэ эрхийг олгох боломжгүй.',
            'password.required'  => 'Нууц үгээ оруулна уу.',
            'password.min'       => 'Нууц үг хамгийн багадаа 8 тэмдэгт байна.',
            'password.confirmed' => 'Нууц үг давтан оруулсантай таарахгүй байна.',
        ];
    }

    public function index()
    {
        $this->me();

        return view('users.index');
    }

    public function create()
    {
        $this->me();

        return view('users.create');
    }

    public function store(Request $request)
    {
        $me = $this->me();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email'    => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in(array_keys($me->assignableRoles()))],
            ...($me->isBranchScoped() ? [] : $this->branchRules($request)),
            ...$this->jobRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->messages());

        $user = DB::transaction(fn () => User::create([
            'name'      => $data['name'],
            ...$this->jobData($data),
            'username'  => $data['username'],
            'email'     => $data['email'] ?? null,
            'role'      => $data['role'],
            // Супер админ бүх салбарыг хариуцна (салбаргүй); Админы бүртгэсэн ажилтан тухайн админы салбарт
            'branch_id' => $me->isBranchScoped() ? $me->branch_id : $this->branchFor($data),
            'password'  => $data['password'],
            'is_active' => $request->boolean('is_active'),
        ]));

        // «Төхөөрөмж олгох» хуудаснаас шинэ ажилтан нэмсэн бол шууд түүнд олгох хэсэг рүү
        if ($request->input('return') === 'bulk-assign' && $user->role === 'employee') {
            return redirect()->route('computers.bulk-assign', ['user_id' => $user->id])
                ->with('success', $user->name.' ажилтан нэмэгдлээ. Одоо төхөөрөмж олгоно уу.');
        }

        return redirect()->route('users.index')->with('success', 'Хэрэглэгч нэмэгдлээ'.($user->branch ? ' · салбар: '.$user->branch->name : '').'.');
    }

    public function edit(User $user)
    {
        abort_unless($this->me()->canEditUser($user), 403);

        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $me = $this->me();
        abort_unless($me->canEditUser($user), 403);

        $isSelf = $me->id === $user->id;

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => [$isSelf ? 'nullable' : 'required', Rule::in(array_keys($me->assignableRoles($user)))],
            ...($isSelf || $me->isBranchScoped() ? [] : $this->branchRules($request)),
            ...$this->jobRules(),
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ], $this->messages());

        $user->name = $data['name'];
        $user->username = $data['username'];
        $user->email = $data['email'] ?? null;
        $user->fill($this->jobData($data));

        // Өөрийн эрх, төлөвийг өөрчилж чадахгүй (superadmin өөрийгөө түгжихээс сэргийлнэ)
        DB::transaction(function () use ($isSelf, $me, $user, $data, $request) {
            if (! $isSelf) {
                $user->role = $data['role'];
                // Админ ажилтныг өөр салбарт шилжүүлж чадахгүй
                if (! $me->isBranchScoped()) {
                    $user->branch_id = $this->branchFor($data);
                }
                // Төхөөрөмжтэй ажилтныг өөр салбарт шилжүүлэхгүй (төхөөрөмж хуучин салбарт, хариуцагч шинэд үлдэнэ)
                if ($user->isDirty('branch_id') && ($held = $this->heldDevices($user))) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'branch_id' => $user->name.'-д '.$held.' төхөөрөмж байна. Салбар солихын өмнө буцаан авах эсвэл шилжүүлнэ үү.',
                    ]);
                }
                $user->is_active = $request->boolean('is_active');
            }

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();
        });

        return redirect()->route('users.index')->with('success', 'Хэрэглэгчийн мэдээлэл шинэчлэгдлээ'.($user->branch_id ? ' · салбар: '.$user->fresh()->branch?->name : '').'.');
    }

    /** Хэрэглэгчид оноогдсон төхөөрөмжийн тоо (бүх салбарын, архивлагдаагүй) */
    private function heldDevices(User $user): int
    {
        return Computer::withoutGlobalScopes()->whereNull('deleted_at')->where('assigned_user_id', $user->id)->count();
    }

    /** Овог, нэгж, алба, албан тушаал — заавал биш */
    private function jobRules(): array
    {
        return array_fill_keys(User::JOB_FIELDS, ['nullable', 'string', 'max:255']);
    }

    private function jobData(array $data): array
    {
        // «＋ Шинэ алба нэмэх…» сонгоод нэр бичээгүй бол алба хоосон
        return collect(User::JOB_FIELDS)->mapWithKeys(fn ($f) => [$f => filled($data[$f] ?? null) && $data[$f] !== '__new__' ? trim($data[$f]) : null])->all();
    }

    public function destroy(User $user)
    {
        $me = $this->me();
        abort_unless($me->canDeleteUser($user), 403);

        // Төхөөрөмжтэй хэрэглэгчийг устгахгүй: төхөөрөмж «Ашиглагдаж байгаа» боловч хариуцагчгүй үлдэнэ
        if ($held = $this->heldDevices($user)) {
            return back()->with('warning', $user->name.'-д '.$held.' төхөөрөмж байна. Устгахын өмнө буцаан авах эсвэл өөр ажилтанд шилжүүлнэ үү.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Хэрэглэгч устгагдлаа.');
    }
}