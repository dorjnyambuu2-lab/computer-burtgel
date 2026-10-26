{{--
    IT ажилтны үйлдлүүд: ажилтанд олгох, буцаан авах, засварт бүртгэх + түүх.
    computers/show.blade.php дотор: @include('computers._actions')
    Шаардлагатай хувьсагч: $computer, $employees, $events (ComputerController::show дамжуулна)
--}}
@php
    $holderName = $computer->assignedUser?->name ?? $computer->assigned_to;
    $hasHolder = filled($holderName);
@endphp

<div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-700">Үйлдлүүд</h2>
        <div class="text-sm text-slate-500">
            Төлөв: <span class="font-medium text-slate-800">{{ $computer->status?->label() ?? '—' }}</span>
            · Хариуцагч: <span class="font-medium text-slate-800">{{ $hasHolder ? $holderName : 'Оноогоогүй' }}</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    {{-- Ажилтанд олгох. Супер админ хариуцагчтай төхөөрөмжийг «Төхөөрөмж шилжүүлэх»-ээр (иж бүрдэлтэй нь) шилжүүлнэ --}}
    @if ($hasHolder && auth()->user()->isSuperadmin())
        <a href="{{ route('transfers.index', ['computer' => $computer->id]) }}"
           class="block rounded-xl bg-blue-600 px-4 py-3 text-center text-sm font-medium !text-white hover:bg-blue-700">Өөр ажилтанд шилжүүлэх</a>
    @else
    <details class="rounded-xl border border-slate-200" @if ($errors->has('user_id')) open @endif>
        <summary class="cursor-pointer select-none rounded-xl bg-blue-600 px-4 py-3 text-center text-sm font-medium text-white hover:bg-blue-700">
            Ажилтанд олгох
        </summary>
        <form method="POST" action="{{ route('computers.assign', $computer) }}" class="space-y-3 p-4">
            @csrf
            <div>
                <label for="assign_user_id" class="mb-1 block text-sm font-medium text-slate-700">Ажилтан</label>
                <select name="user_id" id="assign_user_id" required
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Ажилтан сонгох</option>
                    @foreach ($employees as $employee)
                        @continue($employee->id === $computer->assigned_user_id)
                        <option value="{{ $employee->id }}" @selected((string) old('user_id') === (string) $employee->id)>
                            {{ $employee->name }} ({{ $employee->username ?: $employee->email }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="assign_note" class="mb-1 block text-sm font-medium text-slate-700">Тайлбар (заавал биш)</label>
                <input type="text" name="note" id="assign_note" maxlength="1000"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <button class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Олгох
            </button>
        </form>
    </details>
    @endif

    {{-- Буцаан авах --}}
    @if ($hasHolder)
        <details class="rounded-xl border border-slate-200">
            <summary class="cursor-pointer select-none rounded-xl bg-slate-100 px-4 py-3 text-center text-sm font-medium text-slate-700 hover:bg-slate-200">
                Буцаан авах
            </summary>
            <form method="POST" action="{{ route('computers.return', $computer) }}" class="space-y-3 p-4"
                  onsubmit="return confirm('Компьютерийг {{ $holderName }}-аас буцаан авч, агуулахад оруулах уу?')">
                @csrf
                <p class="text-sm text-slate-500">
                    Хариуцагч цуцлагдаж, төлөв «Агуулахад» болно.
                </p>
                <div>
                    <label for="return_note" class="mb-1 block text-sm font-medium text-slate-700">Тайлбар (заавал биш)</label>
                    <input type="text" name="note" id="return_note" maxlength="1000"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>
                <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">
                    Буцаан авсан
                </button>
            </form>
        </details>
    @endif

    @if ($computer->status === \App\Enums\ComputerStatus::InRepair)
    {{-- Засварын явц: дараалалд #N, явц өөрчлөх / дуусгах --}}
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-center justify-between gap-2">
            <div class="text-sm font-semibold text-amber-900">
                Засварын дараалал <span class="ml-1 rounded bg-amber-200 px-1.5 py-0.5 font-mono">#{{ $computer->repairQueueNumber() }}</span>
            </div>
            <span class="rounded-full px-2 py-0.5 text-xs {{ $computer->repair_stage?->badgeClasses() ?? 'bg-slate-100 text-slate-600' }}">{{ $computer->repair_stage?->label() ?? 'Дараалалд' }}</span>
        </div>
        <form method="POST" action="{{ route('repairs.update', $computer) }}" class="mt-3 space-y-2">
            @csrf @method('PATCH')
            <select name="stage" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" aria-label="Явц">
                @foreach (\App\Enums\RepairStage::options() as $k => $l)
                    <option value="{{ $k }}" @selected($computer->repair_stage?->value === $k)>{{ $l }}</option>
                @endforeach
                <option value="done">✓ Засвар дууссан</option>
            </select>
            <input type="text" name="note" maxlength="1000" placeholder="Тайлбар (заавал биш)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="text" name="cost" inputmode="decimal" placeholder="Зардал ₮ (сэлбэг, ажлын хөлс — заавал биш)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <button class="w-full rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">Явц шинэчлэх</button>
        </form>
        <a href="{{ route('repairs.index') }}" class="mt-2 block text-center text-xs text-amber-800 hover:underline">Бүх дараалал харах →</a>
    </div>
    @else
    {{-- Засварт бүртгэх --}}
    <details class="rounded-xl border border-slate-200" @if ($errors->has('note')) open @endif>
        <summary class="cursor-pointer select-none rounded-xl bg-amber-500 px-4 py-3 text-center text-sm font-medium text-white hover:bg-amber-600">
            Засварт бүртгэх
        </summary>
        <form method="POST" action="{{ route('computers.repair', $computer) }}" class="space-y-3 p-4">
            @csrf
            <div>
                <label for="repair_note" class="mb-1 block text-sm font-medium text-slate-700">Эвдрэл, засварын тайлбар *</label>
                <textarea name="note" id="repair_note" rows="3" required maxlength="1000"
                          placeholder="Жишээ: Дэлгэц анивчаад байна, зарлага өгөхөөс өмнө шалгах"
                          class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">{{ old('note') }}</textarea>
            </div>
            <div>
                <label for="repair_cost" class="mb-1 block text-sm font-medium text-slate-700">Зардал ₮ (мэдэгдэж байвал)</label>
                <input type="text" name="cost" id="repair_cost" inputmode="decimal" value="{{ old('cost') }}" placeholder="Жишээ: 150,000"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <button class="w-full rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600">
                Засварт бүртгэх
            </button>
        </form>
    </details>
    @endif

    {{-- Түүх --}}
    <div class="border-t border-slate-100 pt-4">
        <h3 class="mb-3 text-sm font-semibold text-slate-700">Түүх</h3>
        <div class="space-y-3">
            @forelse ($events as $event)
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 shrink-0 rounded-full px-3 py-1 text-xs {{ $event->badgeClass() }}">{{ $event->label() }}</span>
                    <div class="min-w-0 text-sm">
                        <div class="text-slate-800">
                            @if ($event->user) <span class="font-medium">{{ $event->user->name }}</span> @endif
                            @if ($event->note) <span class="text-slate-600">{{ $event->user ? '· ' : '' }}{{ $event->note }}</span> @endif
                            @if ($event->cost) <span class="ml-1 whitespace-nowrap rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-800">{{ \App\Support\Money::format($event->cost) }}</span> @endif
                        </div>
                        <div class="text-xs text-slate-400">
                            {{ $event->actor?->name ?? '—' }} · {{ $event->created_at->format('Y-m-d H:i') }}
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">Одоогоор түүх бүртгэгдээгүй байна.</p>
            @endforelse
        </div>
    </div>
</div>