@php
    $user = $user ?? new \App\Models\User();
    $isEdit = $user->exists;
    $in = 'mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
    $lb = 'block text-sm font-medium text-slate-700';
    $active = (bool) old('is_active', $isEdit ? $user->is_active : true);
    $me = auth()->user();
    $roles = $me->assignableRoles($isEdit ? $user : null);
    // Шинэ хэрэглэгчийн анхдагч эрх: Супер админд «Админ», Админд «Ажилтан»
    $defaultRole = request('role', array_key_exists('admin', $roles) ? 'admin' : 'employee');
    $branchAdmin = $me->isBranchScoped();
    $isSelf = $isEdit && $user->id === $me->id;
@endphp

<div class="mx-auto max-w-3xl space-y-6">
    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            Мэдээллээ шалгана уу: {{ $errors->count() }} талбарт алдаа байна.
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-base font-bold">Үндсэн мэдээлэл</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="name" class="{{ $lb }}">Нэр <span class="text-rose-500">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="{{ $in }}" placeholder="Бүтэн нэр">
                @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="username" class="{{ $lb }}">Нэвтрэх нэр <span class="text-rose-500">*</span></label>
                <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" required class="{{ $in }}" placeholder="Жишээ: bat.erdene" autocomplete="off">
                @error('username')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="{{ $lb }}">Имэйл <span class="text-rose-500">*</span></label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="{{ $in }}" placeholder="name@company.mn">
                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="role" class="{{ $lb }}">Эрх <span class="text-rose-500">*</span></label>
                @if ($isSelf)
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    <select id="role" class="{{ $in }} bg-slate-50" disabled>
                        <option>{{ \App\Models\User::ROLES[$user->role] ?? $user->role }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Өөрийн эрхийг өөрчлөх боломжгүй.</p>
                @else
                    <select id="role" name="role" class="{{ $in }}" required>
                        @foreach ($roles as $k => $v)
                            <option value="{{ $k }}" @selected(old('role', $user->role ?: $defaultRole) === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    @if ($branchAdmin)
                        <p class="mt-1 text-xs text-slate-500">Та өөрийн салбарын ажилтныг бүртгэнэ.</p>
                    @elseif (! $isEdit)
                        <p class="mt-1 text-xs text-slate-500">Ажилтныг тухайн салбарын админ бүртгэнэ.</p>
                    @endif
                @endif
                @error('role')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    {{-- Ажлын мэдээлэл: компьютерт энэ хэрэглэгчийг хариуцагчаар сонгоход «Байршил ба хариуцагч» хэсэгт автоматаар бөглөгдөнө --}}
    @php
        $jobFields = [
            'surname'    => ['Овог', 'Жишээ: Батын'],
            'unit'       => ['Нэгж', 'Жишээ: Захиргааны нэгж'],
            'department' => ['Алба', 'Жишээ: Мэдээллийн технологийн алба'],
            'position'   => ['Албан тушаал', 'Жишээ: Ахлах мэргэжилтэн'],
        ];
        // Өмнө бичигдсэн утгуудыг санал болгоно (ижил нэрийг өөр өөрөөр бичихээс сэргийлнэ)
        $jobComputerField = ['surname' => 'holder_surname', 'unit' => 'unit', 'department' => 'department', 'position' => 'position'];
        $jobSuggest = fn (string $field) => \App\Models\User::query()->whereNotNull($field)->distinct()->pluck($field)
            ->merge(\App\Models\Computer::query()->whereNotNull($jobComputerField[$field])->distinct()->pluck($jobComputerField[$field]))
            ->map(fn ($v) => trim((string) $v))->filter()->unique()->sort()->values();
    @endphp
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-base font-bold">Ажлын мэдээлэл</h2>
        <p class="mb-4 text-xs text-slate-500">Компьютерт энэ хэрэглэгчийг хариуцагчаар сонгоход эдгээр нь автоматаар бөглөгдөнө.</p>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($jobFields as $field => [$label, $placeholder])
                <div>
                    <label for="{{ $field }}" class="{{ $lb }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field, $user->$field) }}" maxlength="255"
                           list="{{ $field }}-options" class="{{ $in }}" placeholder="{{ $placeholder }}" autocomplete="off">
                    <datalist id="{{ $field }}-options">
                        @foreach ($jobSuggest($field) as $option)
                            <option value="{{ $option }}"></option>
                        @endforeach
                    </datalist>
                    @error($field)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </section>

    {{-- Хариуцах / харьяалах салбар: Админ зөвхөн энэ салбарын төхөөрөмж, ажилтныг харж удирдана --}}
    @php
        $branches = \App\Models\Branch::query()->withCount(['computers', 'admins', 'users as employees_count' => fn ($q) => $q->where('role', 'employee')])->orderBy('name')->get();
        $branchValue = (string) old('branch_id', $user->branch_id ?? request('branch_id'));
        // Салбаргүй (шинэ) хэрэглэгчид урьдчилан сонгох салбар: админд админгүй, ажилтанд цөөн ажилтантай (Branch::autoFor)
        $suggest = collect(['admin', 'employee'])->mapWithKeys(fn ($r) => [$r => (string) \App\Models\Branch::autoFor($r, $isEdit ? $user : null)?->id]);
    @endphp
    @if ($branchAdmin)
        {{-- Админ: ажилтан үргэлж өөрийн салбарт --}}
        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-700">
            <h2 class="mb-1 text-base font-bold text-slate-900">Салбар</h2>
            <b>{{ $me->branch?->name }}</b> — таны салбар. Ажилтан энэ салбарт бүртгэгдэнэ.
        </section>
    @else
    <section id="branch-section" class="rounded-2xl border-2 border-blue-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-base font-bold">Хариуцах салбар <span class="text-rose-500">*</span></h2>
        <p id="branch-help" class="mb-4 text-xs text-slate-500"></p>

        <div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($branches as $b)
                    {{-- Салбар бүрийн нэрийг энд шууд засаж болно (✎ Засах) --}}
                    <div class="relative rounded-xl border border-slate-200 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50" data-branch-card
                         data-update-url="{{ route('branches.update', $b) }}" data-name="{{ $b->name }}" data-code="{{ $b->code }}">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl p-3 pr-16 hover:bg-slate-50/60">
                            <input type="radio" name="branch_id" value="{{ $b->id }}" class="mt-0.5 h-4 w-4" @checked($branchValue === (string) $b->id) @disabled($isSelf)>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900" data-branch-name>{{ $b->label() }}</span>
                                <span class="block text-xs text-slate-500">{{ $b->computers_count }} төхөөрөмж · {{ $b->admins_count }} админ · {{ $b->employees_count }} ажилтан{{ $b->address ? ' · '.$b->address : '' }}</span>
                            </span>
                        </label>
                        <button type="button" data-rename class="absolute right-2 top-2 !rounded-lg !border-0 !bg-transparent !px-2 !py-1 !text-xs font-medium !text-blue-600 hover:!bg-blue-100">✎ Засах</button>
                        <div data-rename-form class="hidden border-t border-slate-200 p-3">
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" data-rename-name maxlength="255" placeholder="Салбарын нэр" aria-label="Салбарын шинэ нэр" class="{{ $in }} !mt-0 col-span-2">
                                <input type="text" data-rename-code maxlength="20" placeholder="Код" aria-label="Салбарын код" class="{{ $in }} !mt-0 uppercase">
                            </div>
                            <p data-rename-error class="mt-1 hidden text-xs text-rose-600"></p>
                            <div class="mt-2 flex justify-end gap-2">
                                <button type="button" data-rename-cancel class="!rounded-lg !px-3 !py-1.5 !text-xs">Болих</button>
                                <button type="button" data-rename-save class="!rounded-lg !border-blue-600 !bg-blue-600 !px-3 !py-1.5 !text-xs !text-white">Нэр хадгалах</button>
                            </div>
                        </div>
                    </div>
                @endforeach
                {{-- Жагсаалтад байхгүй бол шинэ салбарын нэрийг гараар бичнэ (хадгалахад салбар үүснэ) --}}
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-dashed border-slate-300 p-3 hover:bg-slate-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 sm:col-span-2">
                    <input type="radio" name="branch_id" value="new" class="mt-0.5 h-4 w-4" @checked($branchValue === 'new') @disabled($isSelf)>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-900">+ Шинэ салбар</span>
                        <span class="block text-xs text-slate-500">Жагсаалтад байхгүй бол нэрийг нь бичнэ, хадгалахад салбар үүснэ</span>
                        <span id="new-branch-fields" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <input type="text" name="new_branch_name" id="new_branch_name" value="{{ old('new_branch_name') }}" maxlength="255" placeholder="Салбарын нэр (жишээ: Дархан салбар)" class="{{ $in }} !mt-0 sm:col-span-2" @disabled($isSelf)>
                            <input type="text" name="new_branch_code" value="{{ old('new_branch_code') }}" maxlength="20" placeholder="Код (заавал биш)" class="{{ $in }} !mt-0 uppercase" @disabled($isSelf)>
                        </span>
                    </span>
                </label>
            </div>
            @error('new_branch_name')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
            @error('new_branch_code')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <p id="branch-summary" class="mt-4 hidden rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-900"></p>
        @error('branch_id')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
    </section>
    <section id="branch-all" class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
        <b>Супер админ</b> бүх салбарыг хариуцдаг тул салбар сонгох шаардлагагүй.
    </section>
    <script>
    // Эрхээс хамааран: Админ — хариуцах салбар (заавал), Ажилтан — харьяалах салбар (заавал), Супер админ — бүх салбар.
    // Жагсаалтаас сонгоно эсвэл «+ Шинэ салбар»-ын нэрийг бичнэ.
    (function () {
        const role = document.getElementById('role');
        const section = document.getElementById('branch-section');
        const all = document.getElementById('branch-all');
        const help = document.getElementById('branch-help');
        const summary = document.getElementById('branch-summary');
        const radios = Array.from(section.querySelectorAll('input[name="branch_id"]'));
        const newName = document.getElementById('new_branch_name');
        const newFields = document.getElementById('new-branch-fields');
        const suggest = @json($suggest);
        if (!role) return;

        // Салбар сонгоогүй бол санал болгох салбарыг урьдчилан сонгоно (эрх солигдоход ч)
        function preselect() {
            if (radios.some(function (x) { return x.checked; })) return;
            const id = suggest[role.value === 'admin' ? 'admin' : 'employee'];
            const r = radios.find(function (x) { return x.value === id; });
            if (r && !r.disabled) r.checked = true;
        }

        function update() {
            const r = role.value;
            const superadmin = r === 'superadmin';
            section.classList.toggle('hidden', superadmin);
            all.classList.toggle('hidden', !superadmin);
            radios.forEach(function (x) { x.required = !superadmin && !x.disabled; });

            const picked = radios.find(function (x) { return x.checked; });
            const isNew = !!picked && picked.value === 'new';
            newFields.classList.toggle('hidden', !isNew);
            if (newName) newName.required = !superadmin && isNew && !newName.disabled;

            help.textContent = r === 'admin'
                ? 'Энэ админ ЗӨВХӨН хариуцах салбарынхаа төхөөрөмж, ажилтан, засвар, хүсэлтийг харж удирдана. Бусад салбар харагдахгүй.'
                : 'Ажилтны харьяалах салбар: тухайн салбарын админ энэ ажилтанд төхөөрөмж олгож чадна.';

            const name = isNew ? (newName.value.trim() || 'шинэ салбар') : (picked ? picked.closest('label').querySelector('[data-branch-name]').textContent : '');
            summary.classList.toggle('hidden', !name || superadmin);
            summary.innerHTML = '';
            if (name) summary.append('✓ ' + (r === 'admin' ? 'Хариуцах салбар: ' : 'Харьяалах салбар: '), Object.assign(document.createElement('b'), { textContent: name }));
        }

        role.addEventListener('change', function () { preselect(); update(); });

        // ✎ Нэр засах: салбарын нэр, кодыг шууд хадгална (бусад хэрэглэгч, төхөөрөмжид ч шинэ нэр харагдана)
        const token = (section.closest('form').querySelector('input[name="_token"]') || {}).value;
        section.querySelectorAll('[data-branch-card]').forEach(function (card) {
            const box = card.querySelector('[data-rename-form]');
            const nameIn = card.querySelector('[data-rename-name]');
            const codeIn = card.querySelector('[data-rename-code]');
            const err = card.querySelector('[data-rename-error]');
            const save = card.querySelector('[data-rename-save]');
            const open = function (show) {
                box.classList.toggle('hidden', !show);
                err.classList.add('hidden');
                if (show) { nameIn.value = card.dataset.name; codeIn.value = card.dataset.code; nameIn.focus(); nameIn.select(); }
            };
            card.querySelector('[data-rename]').addEventListener('click', function () { open(box.classList.contains('hidden')); });
            card.querySelector('[data-rename-cancel]').addEventListener('click', function () { open(false); });
            [nameIn, codeIn].forEach(function (i) {
                // Enter дарахад хэрэглэгчийн форм илгээгдэхгүй, зөвхөн нэр хадгална
                i.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); save.click(); }
                    if (e.key === 'Escape') { e.preventDefault(); open(false); }
                });
            });
            save.addEventListener('click', function () {
                save.disabled = true;
                const body = new URLSearchParams({ _method: 'PUT', name: nameIn.value.trim(), code: codeIn.value.trim() });
                fetch(card.dataset.updateUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                }).then(function (res) {
                    return res.json().then(function (data) { return { ok: res.ok, data: data }; });
                }).then(function (r) {
                    if (!r.ok) throw new Error(r.data.errors ? Object.values(r.data.errors)[0][0] : (r.data.message || 'Хадгалж чадсангүй.'));
                    card.dataset.name = r.data.name;
                    card.dataset.code = r.data.code || '';
                    card.querySelector('[data-branch-name]').textContent = r.data.label;
                    open(false);
                    update();
                }).catch(function (e) {
                    err.textContent = e.message;
                    err.classList.remove('hidden');
                }).finally(function () { save.disabled = false; });
            });
        });
        radios.forEach(function (x) { x.addEventListener('change', update); });
        if (newName) newName.addEventListener('input', update);
        preselect();
        update();
    })();
    </script>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-base font-bold">Нууц үг</h2>
        @if ($isEdit)<p class="mb-4 text-xs text-slate-500">Солихгүй бол хоосон үлдээнэ.</p>@else<p class="mb-4 text-xs text-slate-500">Хамгийн багадаа 8 тэмдэгт.</p>@endif
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="password" class="{{ $lb }}">Нууц үг @unless($isEdit)<span class="text-rose-500">*</span>@endunless</label>
                <input id="password" name="password" type="password" @required(! $isEdit) autocomplete="new-password" class="{{ $in }}">
                @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="{{ $lb }}">Нууц үг давтах</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $in }}">
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-3 text-base font-bold">Төлөв</h2>
        <input type="hidden" name="is_active" value="0">
        <label class="inline-flex cursor-pointer items-center gap-3 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked($active) @disabled($isSelf) class="h-4 w-4 rounded border-slate-300">
            Идэвхтэй (системд нэвтрэх эрхтэй)
        </label>
        @error('is_active')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </section>

    <div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-slate-200 bg-white/90 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
        <a href="{{ url('/users') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold !text-slate-700 hover:bg-slate-100">Цуцлах</a>
        <button type="submit" class="rounded-xl border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
            {{ $isEdit ? 'Өөрчлөлтийг хадгалах' : 'Хадгалах' }}
        </button>
    </div>
</div>