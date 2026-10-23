<?php

namespace App\Models;

use App\Enums\ComputerStatus;
use App\Enums\ComputerType;
use App\Enums\EquipmentCategory;
use App\Enums\RepairStage;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Computer extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'inventory_number', 'name', 'type', 'brand', 'model', 'serial_number',
        'cpu', 'ram_gb', 'storage', 'os', 'ip_address', 'mac_address', 'specs',
        'category', 'department', 'unit', 'position', 'location', 'assigned_to', 'assigned_user_id', 'holder_surname', 'holder_name', 'status',
        'purchase_date', 'purchase_price', 'warranty_until', 'notes',
        'kit_parent_id', 'kit_index', 'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ComputerType::class,
            'category' => EquipmentCategory::class,
            'status' => ComputerStatus::class,
            'repair_stage' => RepairStage::class,
            'repair_position' => 'integer',
            'repair_queued_at' => 'datetime',
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'warranty_until' => 'date',
            'network_updated_at' => 'datetime',
            'ram_gb' => 'integer',
            'assigned_user_id' => 'integer',
            'created_by' => 'integer',
            'sequence_number' => 'integer',
            'kit_parent_id' => 'integer',
            'branch_id' => 'integer',
            'kit_index' => 'integer',
        ];
    }

    /**
     * `assigned_user_id` (жинхэнэ холбоос) болон `assigned_to` (харуулах текст)-ийг тааруулж хадгална.
     *
     *  - Хариуцагчийг ID-аар сонгосон бол: assigned_to = хэрэглэгчийн нэр болно.
     *  - Хуучин кодоор текстээр оноосон бол (жишээ нь шилжүүлгийн баталгаажуулалт):
     *    текстээр хэрэглэгчийг хайж assigned_user_id-г нөхнө.
     *  - Хариуцагч солигдвол "хүлээн авсан" баталгаа дахин null болно.
     */
    protected static function booted(): void
    {
        // Админ зөвхөн өөрийн салбарын төхөөрөмжийг харна (App\Models\Scopes\BranchScope)
        static::addGlobalScope(new Scopes\BranchScope);

        static::creating(function (Computer $computer) {
            $computer->created_by ??= Auth::id();

            // Салбар: Админ бүртгэвэл заавал өөрийн салбар; бусад үед сонгосон, эсвэл ганц салбар байвал түүнийг
            /** @var User|null $actor */
            $actor = Auth::user();
            if ($actor?->isAdmin()) {
                $computer->branch_id = $actor->branch_id;
            } elseif (! $computer->branch_id && ($parent = $computer->kit_parent_id ? static::global()->find($computer->kit_parent_id) : null)) {
                $computer->branch_id = $parent->branch_id; // иж бүрдлийн дагалдах хэрэгсэл ижил салбарт
            }
            $computer->branch_id ??= Branch::query()->count() === 1 ? Branch::query()->value('id') : null;

            // Дэс дугаар: тухайн салбарын дараагийн дугаар (салбар бүр #1, #2, #3 ...)
            $computer->sequence_number ??= static::nextSequenceNumber($computer->branch_id);

            // Давтагдашгүй QR дугаар (түүхээс сэргээхэд хуучин дугаараа авна, өөр төхөөрөмжид олгогдсон бол шинээр)
            if (blank($computer->qr_code) || static::global()->where('qr_code', $computer->qr_code)->exists()) {
                $computer->qr_code = static::newQrCode();
            }

            // Иж бүрдэл: үндсэн компьютер -1, дагалдах хэрэгслүүд -2, -3 ... (PC-20261020-001-1, PC-20261020-001-2 ...)
            // Иж бүрдэлд 4 төхөөрөмж бол дэд дугаар 1-4 хүртэл.
            if ($computer->kit_parent_id && ($parent = static::global()->find($computer->kit_parent_id))) {
                $parent->claimKitMainNumber();
                $computer->kit_index ??= static::nextKitIndex($parent);
                if (blank($computer->inventory_number)) {
                    $computer->inventory_number = $parent->kitBase().'-'.$computer->kit_index;
                }
            }

            // Бүртгэлийн дугаар, нэрийг оруулаагүй бол автоматаар үүсгэнэ: SA1-20261023-001
            // (бүртгэсэн хүн + огноо + тухайн өдрийн дугаар)
            if (blank($computer->inventory_number)) {
                $computer->inventory_number = static::nextInventoryNumber($computer->created_by);
            }
            if (blank($computer->name)) {
                $computer->name = trim(($computer->brand ?? '').' '.($computer->model ?? ''))
                    ?: (($computer->category?->label() ?? $computer->type?->label() ?? 'Төхөөрөмж').' '.$computer->inventory_number);
            }
        });

        // Гараар бичсэн утга: «dell» → жагсаалтын «Dell» болгож хадгална. Шинэ утга дараагийн бүртгэлд
        // сонголтын жагсаалтад автоматаар гарна (App\Support\SpecSuggestions)
        static::saving(fn (Computer $computer) => \App\Support\SpecSuggestions::canonicalize($computer));
        static::saved(fn () => \App\Support\SpecSuggestions::flush());

        // MAC-г нэг хэлбэрээр хадгална: aa-bb-cc-dd-ee-ff → AA:BB:CC:DD:EE:FF
        static::saving(function (Computer $computer) {
            if ($computer->isDirty('mac_address')) {
                $computer->mac_address = self::normalizeMac($computer->mac_address) ?? (filled($computer->mac_address) ? $computer->mac_address : null);
            }
        });

        // Нэг IP нэг зэрэг ганц компьютерт байна (өгөгдлийн санд unique): ажилтан нэвтрэхэд автоматаар авсан IP
        // өөр компьютерт бүртгэлтэй бол тэр нь хуучирсан (DHCP дахин олгосон) тул хадгалахаас өмнө чөлөөлнө.
        // Формоор бичихэд UniqueNetworkAddress алдаа өгнө.
        static::saving(function (Computer $computer) {
            if ($computer->isDirty('ip_address') && filled($computer->ip_address)) {
                static::withoutGlobalScopes()->whereNull('deleted_at')
                    ->where('ip_address', $computer->ip_address)
                    ->when($computer->exists, fn ($q) => $q->whereKeyNot($computer->id))
                    ->get()->each(function (Computer $old) {
                        $old->ip_address = null;
                        $old->save(); // үйлдлийн түүхэнд бичигдэнэ
                    });
            }
        });

        // Хогийн сав руу хийхэд IP, MAC-г чөлөөлнө — тэр хаягаар шинэ төхөөрөмж бүртгэж болно (хуучин утга түүхэнд үлдэнэ)
        static::deleted(function (Computer $computer) {
            if (! $computer->isForceDeleting() && ($computer->ip_address || $computer->mac_address)) {
                $computer->ip_address = null;
                $computer->mac_address = null;
                $computer->save();
            }
        });

        // Засварын дараалал: төлөв «Засварт» болмогц дарааллын төгсгөлд орж, өөр төлөвт шилжихэд гарна.
        // Ингэснээр засах форм, засварт бүртгэх товч, хүсэлтээс гээд аль ч замаар орсон дараалал зөв байна.
        static::saving(function (Computer $computer) {
            if (! $computer->isDirty('status')) {
                return;
            }

            if ($computer->status === ComputerStatus::InRepair) {
                if ($computer->repair_position === null) {
                    $computer->repair_position = (int) static::query()->max('repair_position') + 1;
                    $computer->repair_stage = RepairStage::Queued;
                    $computer->repair_queued_at = now();
                }
            } else {
                $computer->repair_position = null;
                $computer->repair_stage = null;
                $computer->repair_queued_at = null;
            }
        });

        // Дэс дугаар завсаргүй байна: устгавал (архивлах, бүрмөсөн) дараагийнх нь нэгээр ухарна,
        // сэргээвэл төгсгөлд нь шинэ дугаар авна
        static::deleted(fn () => static::resequence());
        static::restored(fn () => static::resequence());

        // Хариуцагчийг нэг ажилтнаас нөгөөд солих нь шилжүүлэг: эрх зөвхөн салбарын Админд («Төхөөрөмж шилжүүлэх»).
        // Супер админ хариуцагчгүй төхөөрөмж олгох, буцаан авах боломжтой. Олгох, олноор олгох, засах форм аль замаар ч хамаарна.
        static::updating(function (Computer $computer) {
            $from = $computer->getOriginal('assigned_user_id');
            /** @var User|null $actor */
            $actor = Auth::user();
            if ($actor?->isSuperadmin() && $computer->isDirty('assigned_user_id')
                && $from && $computer->assigned_user_id && (int) $from !== (int) $computer->assigned_user_id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_id' => $computer->activityLabel().' ажилтанд оноогдсон байна. Өөр ажилтанд шилжүүлэх эрх зөвхөн салбарын Админд байна.',
                ]);
            }
        });

        // Салбар солигдвол шинэ салбарынхаа төгсгөлд дугаар авч, хуучин салбарын дугаар нөхөгдөнө
        static::updating(function (Computer $computer) {
            if ($computer->isDirty('branch_id') && ! $computer->isDirty('sequence_number') && ! $computer->trashed()) {
                $computer->sequence_number = static::nextSequenceNumber($computer->branch_id);
            }
        });
        static::updated(function (Computer $computer) {
            if ($computer->wasChanged('branch_id')) {
                static::resequence();
            }
        });

        static::saving(function (Computer $computer) {
            // Хариуцагчгүй компьютерт бичсэн овог, нэр салбарын ганц хэрэглэгчтэй таарвал тэр ажилтанд олгоно
            // (буцааж авч хариуцагчийг арилгаж буй үед биш)
            if (! $computer->assigned_user_id && ! $computer->getOriginal('assigned_user_id') && ($owner = $computer->matchHolderUser())) {
                $computer->assigned_user_id = $owner->id;
            }

            $ownerChanged = $computer->isDirty('assigned_user_id')
                && $computer->assigned_user_id != $computer->getOriginal('assigned_user_id');

            if ($ownerChanged) {
                $owner = $computer->assigned_user_id ? User::find($computer->assigned_user_id) : null;
                $computer->assigned_to = $owner?->name;
                $computer->acknowledged_at = null;

                // Хариуцагчийн овог, нэрийг профайлаас авна (буцааж авахад арилна);
                // нэгж, алба, албан тушаалыг профайлд бөглөгдсөн бол шинэ хариуцагчийнхаар солино
                $computer->holder_surname = $owner?->surname;
                $computer->holder_name = $owner?->name;
                foreach (['unit', 'department', 'position'] as $field) {
                    if (filled($owner?->$field)) {
                        $computer->$field = $owner->$field;
                    }
                }
            } elseif ($computer->isDirty('assigned_to')) {
                $previous = $computer->assigned_user_id;
                $computer->assigned_user_id = User::findByIdentifier($computer->assigned_to)?->id;

                if ($computer->assigned_user_id != $previous) {
                    $computer->acknowledged_at = null;
                }
            }
        });
    }

    /**
     * «Овог», «Нэр» талбарт бичсэн хүнтэй таарах системийн хэрэглэгч:
     * тухайн салбарын идэвхтэй хэрэглэгчдээс нэр (овог бичсэн бол овог нь ч) таарах ганц хүн. Олон бол null.
     */
    /** MAC-г AA:BB:CC:DD:EE:FF хэлбэрт; буруу бол null */
    public static function normalizeMac(?string $mac): ?string
    {
        $mac = strtoupper(str_replace('-', ':', trim((string) $mac)));

        return preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac) && $mac !== 'FF:FF:FF:FF:FF:FF' ? $mac : null;
    }

    /**
     * Санамсаргүй (random / locally administered) MAC эсэх: эхний байтын 2-р бит асаалттай —
     * 2-р тэмдэгт нь 2, 6, A, E. Windows-ийн «Random hardware addresses», виртуал машин ийм MAC өгдөг
     * бөгөөд хожим өөрчлөгддөг тул компьютерийг таних боломжгүй.
     */
    public static function isRandomMac(?string $mac): bool
    {
        $mac = self::normalizeMac($mac);

        return $mac !== null && in_array($mac[1], ['2', '6', 'A', 'E'], true);
    }

    public function matchHolderUser(): ?User
    {
        $name = trim((string) $this->holder_name);
        // Шинэ бүртгэлд салбарыг дараа нь (creating) онооно — Админы салбараар тооцно
        $branchId = $this->branch_id ?? \Illuminate\Support\Facades\Auth::user()?->branch_id;
        if ($name === '' || ! $branchId) {
            return null;
        }
        $surname = trim((string) $this->holder_surname);

        $matches = User::query()
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'employee'])
            ->where('branch_id', $branchId)
            ->where('name', $name)
            ->when($surname !== '', fn ($q) => $q->where(fn ($w) => $w->where('surname', $surname)->orWhereNull('surname')->orWhere('surname', '')))
            ->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Бүх салбарын (устгасан ч) төхөөрөмж. Бүртгэлийн дугаар, дэс дугаар, серийн дугаар зэрэг
     * бүх салбарт давхардах ёсгүй утгыг шалгахад ашиглана (Админы салбарын шүүлтгүй).
     */
    public static function global(): Builder
    {
        return static::withoutGlobalScope(Scopes\BranchScope::class)->withTrashed();
    }

    /**
     * Бүртгэсэн хүний тухайн өдрийн дараагийн дугаар: SA1-20261023-001, -002 ... (ангиллаас үл хамааран,
     * устгагдсан ч тоологдоно). Иж бүрдлийн дэд дугаар (-1, -2) тусдаа тоологдохгүй.
     */
    public static function nextDailySerial(string $registrarCode, ?\DateTimeInterface $date = null): int
    {
        $prefix = $registrarCode.'-'.($date ?? now())->format('Ymd').'-';
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)(?:-\d+)?$/';

        return 1 + (int) static::global()
            ->where('inventory_number', 'like', $prefix.'%')
            ->pluck('inventory_number')
            ->map(fn ($number) => preg_match($pattern, (string) $number, $m) ? (int) $m[1] : 0)
            ->max();
    }

    /** Бүртгэлийн дугаар угсрах: {бүртгэсэн хүн}-{огноо}-{дугаар}, жишээ нь SA1-20261023-001 */
    public static function formatInventoryNumber(string $registrarCode, int $serial, ?\DateTimeInterface $date = null): string
    {
        return sprintf('%s-%s-%03d', $registrarCode, ($date ?? now())->format('Ymd'), $serial);
    }

    /**
     * Шинэ давтагдашгүй QR дугаар: 10 оронтой санамсаргүй тоо (жишээ нь 4829135702).
     * Өмнө олгогдсон (устгагдсан ч) дугаартай давхцахгүй.
     */
    public static function newQrCode(): string
    {
        do {
            $code = (string) random_int(1_000_000_000, 9_999_999_999);
        } while (DB::table('computers')->where('qr_code', $code)->exists());

        return $code;
    }

    /** QR код доторх утга: төхөөрөмжийн давтагдашгүй QR дугаар */
    public function qrContent(): string
    {
        if (blank($this->qr_code)) {
            $this->forceFill(['qr_code' => static::newQrCode()])->saveQuietly();
        }

        return $this->qr_code;
    }

    /** Дараагийн чөлөөтэй бүртгэлийн дугаар: бүртгэсэн хүний код (SA1, A2) + огноо + тухайн өдрийн дугаар */
    public static function nextInventoryNumber(?int $registrarId): string
    {
        $code = User::registrarCode($registrarId);

        return static::formatInventoryNumber($code, static::nextDailySerial($code));
    }

    /**
     * Дараагийн дэс дугаар = тухайн салбарын хамгийн их дугаар + 1 (салбар бүр 1-ээс эхэлнэ, Админ өөрийн
     * салбарын жагсаалтад завсаргүй #1, #2 ... харна). Тоолуурын мөрийг түгжиж (lockForUpdate) хоёр админ
     * зэрэг бүртгэхэд ижил дугаар олгохгүй. Бодит бүртгэлийг тоолдог тул баазаас шууд устгасан ч завсаргүй.
     */
    public static function nextSequenceNumber(?int $branchId = null): int
    {
        return DB::transaction(function () use ($branchId) {
            static::lockSequenceCounter();

            return static::peekSequenceNumber($branchId);
        });
    }

    /** Формд урьдчилан харуулах дараагийн дэс дугаар (тоолуурыг өөрчлөхгүй) */
    public static function peekSequenceNumber(?int $branchId = null): int
    {
        return (int) static::global()->where('branch_id', $branchId)->max('sequence_number') + 1;
    }

    /**
     * Идэвхтэй компьютеруудыг 1, 2, 3 ... гэж завсаргүй дугаарлана (одоогийн дарааллыг хадгална).
     * Устгагдсан (архивласан) компьютер дугааргүй болно, сэргээвэл төгсгөлд нь дугаар авна.
     */
    public static function resequence(): void
    {
        DB::transaction(function () {
            static::lockSequenceCounter();

            DB::table('computers')->whereNotNull('deleted_at')->whereNotNull('sequence_number')
                ->update(['sequence_number' => null]);

            // Салбар бүрт тусдаа 1, 2, 3 ... Дугаар зөвхөн буурдаг (эсвэл дугааргүй нь төгсгөлд нэмэгддэг)
            // тул (branch_id, sequence_number) unique зөрчигдөхгүй
            $rows = DB::table('computers')->whereNull('deleted_at')
                ->orderBy('branch_id')->orderByRaw('sequence_number IS NULL')->orderBy('sequence_number')->orderBy('id')
                ->get(['id', 'branch_id', 'sequence_number']);

            $counters = [];
            foreach ($rows as $row) {
                $n = $counters[(int) $row->branch_id] = ($counters[(int) $row->branch_id] ?? 0) + 1;
                if ((int) $row->sequence_number !== $n) {
                    DB::table('computers')->where('id', $row->id)->update(['sequence_number' => $n]);
                }
            }

            DB::table('sequences')->where('name', 'computers')->update(['value' => $rows->count()]);
        });
    }

    /** Тоолуурын мөрийг түгжиж утгыг нь буцаана (байхгүй бол үүсгэнэ) */
    private static function lockSequenceCounter(): int
    {
        $current = DB::table('sequences')->where('name', 'computers')->lockForUpdate()->value('value');

        if ($current === null) {
            $current = (int) static::global()->max('sequence_number');
            DB::table('sequences')->insert(['name' => 'computers', 'value' => $current]);
        }

        return (int) $current;
    }

    /** Иж бүрдлийн үндсэн компьютер болж болох ангилал (ангилалгүй бүртгэл ч компьютер) */
    public const KIT_MAIN_CATEGORIES = ['processor', 'laptop'];

    public function canBeKitMain(): bool
    {
        return ! $this->kit_parent_id && (! $this->category || in_array($this->category->value, self::KIT_MAIN_CATEGORIES, true));
    }

    /**
     * Дагалдах хэрэгслийн дараагийн дэд дугаар: 1 нь үндсэн компьютерийнх тул 2-оос эхэлнэ
     * (устгасан ч дугаар нь дахин олгогдохгүй).
     */
    public static function nextKitIndex(Computer $parent): int
    {
        return max(1, (int) static::global()->where('kit_parent_id', $parent->id)->max('kit_index')) + 1;
    }

    /** Иж бүрдлийн суурь дугаар (дэд дугааргүй): PC-20261020-001-1 → PC-20261020-001 */
    public function kitBase(): string
    {
        $number = (string) $this->inventory_number;

        return $this->kit_index === 1 && ! $this->kit_parent_id && str_ends_with($number, '-1')
            ? substr($number, 0, -2)
            : $number;
    }

    /**
     * Анхны дагалдах хэрэгсэл нэмэгдэхэд үндсэн компьютер иж бүрдлийн 1-р дэд дугаарыг авна:
     * PC-20261020-001 → PC-20261020-001-1 (QR код нь давтагдашгүй QR дугаараар тул шошго хүчинтэй хэвээр).
     */
    public function claimKitMainNumber(): void
    {
        if ($this->kit_index !== null || $this->kit_parent_id) {
            return;
        }

        $this->kit_index = 1;
        $numbered = $this->inventory_number.'-1';
        if (! static::global()->where('inventory_number', $numbered)->exists()) {
            $this->inventory_number = $numbered;
        }
        $this->save();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Үндсэн компьютер (дагалдах хэрэгсэл бол) */
    public function kitParent(): BelongsTo
    {
        return $this->belongsTo(Computer::class, 'kit_parent_id')->withTrashed();
    }

    /** Дагалдах хэрэгслүүд (үндсэн компьютер бол), дэд дугаараар */
    public function kitItems(): HasMany
    {
        return $this->hasMany(Computer::class, 'kit_parent_id')->orderBy('kit_index');
    }

    /** Жагсаалтад харагдах дэс дугаар: #12 */
    public function sequenceLabel(): string
    {
        return $this->sequence_number ? '#'.$this->sequence_number : '—';
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }


    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /** Засварын дараалал: эхэнд орсон нь эхэнд */
    public function scopeRepairQueue(Builder $query): Builder
    {
        return $query->where('status', ComputerStatus::InRepair)
            ->orderByRaw('repair_position is null')
            ->orderBy('repair_position')
            ->orderBy('id');
    }

    /**
     * Дараалал дахь дэс дугаар: [computer_id => 1, 2, 3 ...].
     * Дугаар нь хадгалсан байр биш, эрэмбэ тул дундаас нь гарсан ч #1-ээс тасралтгүй үргэлжилнэ.
     *
     * @return array<int,int>
     */
    public static function repairQueueNumbers(): array
    {
        return static::query()->repairQueue()->pluck('id')
            ->values()
            ->mapWithKeys(fn ($id, $i) => [$id => $i + 1])
            ->all();
    }

    /** Энэ компьютерийн дарааллын дугаар (засварт биш бол null) */
    public function repairQueueNumber(): ?int
    {
        return static::repairQueueNumbers()[$this->getKey()] ?? null;
    }

    /** Олгосон / буцаасан / засварт бүртгэсэн түүх (хамгийн сүүлийнх нь эхэнд) */
    public function events(): HasMany
    {
        return $this->hasMany(ComputerEvent::class)->latest('id');
    }

    /**
     * Зардлын тооцоо: худалдан авсан үнэ, засварын нийт зардал (засварын түүхэнд оруулсан дүнгийн нийлбэр),
     * засвар хэдэн удаа хийгдсэн, нийт өртөг (үнэ + засвар).
     *
     * @return array{price: ?float, repair_cost: float, repair_count: int, total: float}
     */
    public function costSummary(): array
    {
        $repairs = $this->events()->reorder()->whereIn('type', ['repair', 'repair_update', 'repaired']);
        $repairCost = (float) (clone $repairs)->sum('cost');
        $price = $this->purchase_price !== null ? (float) $this->purchase_price : null;

        return [
            'price' => $price,
            'repair_cost' => $repairCost,
            // Төлөв «Засварт» болсон удаа (засварын дараалал, засах форм аль алинаас) — Тайлантай ижил
            'repair_count' => DB::table('activity_logs')->where('subject_id', $this->id)->where('subject_type', 'like', '%Computer')
                ->whereIn('action', ['created', 'updated'])
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(changes, '$.status.new')) = ?", [ComputerStatus::InRepair->value])
                ->count(),
            'total' => ($price ?? 0) + $repairCost,
        ];
    }

    /** Үйлдлийн түүх, QR шошго дээр харагдах нэр: "Нэр [Бүртгэлийн дугаар]" */
    public function activityLabel(): string
    {
        $label = trim(($this->name ?? '').($this->inventory_number ? ' ['.$this->inventory_number.']' : ''));

        return $label !== '' ? $label : 'Компьютер #'.$this->getKey();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        $term = trim($term);

        // 10 оронтой QR дугаараар хайвал яг тэр төхөөрөмж
        if (preg_match('/^\d{10}$/', $term) && (clone $query)->where('qr_code', $term)->exists()) {
            return $query->where('qr_code', $term);
        }

        // "#12" гэж хайвал дэс дугаараар нь яг олно
        if (preg_match('/^#(\d+)$/', $term, $m)) {
            return $query->where('sequence_number', (int) $m[1]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            foreach (['inventory_number', 'name', 'brand', 'model', 'serial_number',
                'ip_address', 'mac_address', 'cpu', 'specs', 'assigned_to', 'department', 'unit', 'position', 'location', 'holder_surname', 'holder_name'] as $col) {
                $q->orWhere($col, 'like', $like);
            }
        });
    }

    public function scopeWarrantyExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('warranty_until')
            ->whereBetween('warranty_until', [today(), today()->addDays($days)]);
    }

    public function scopeWarrantyExpired(Builder $query): Builder
    {
        return $query->whereNotNull('warranty_until')->where('warranty_until', '<', today());
    }

    public function warrantyState(): ?string
    {
        if (! $this->warranty_until) {
            return null;
        }
        if ($this->warranty_until->lt(today())) {
            return 'expired';
        }

        return $this->warranty_until->lte(today()->addDays(60)) ? 'expiring' : 'valid';
    }
}