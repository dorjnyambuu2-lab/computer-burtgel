<?php

namespace Database\Seeders;

use App\Enums\ComputerStatus;
use App\Enums\ComputerType;
use App\Enums\EquipmentCategory;
use App\Enums\RepairStage;
use App\Models\AssetRequest;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Models\ComputerTransfer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Танилцуулах (demo) орчны жишээ өгөгдөл: 3 салбар, админ, ажилтнууд, ~80 төхөөрөмж, засварын дараалал,
 * эвдрэлийн мэдээлэл, захиалга, шилжүүлэг. Төхөөрөмж огт байхгүй үед л ажиллана — бодит өгөгдөлтэй санд юу ч хийхгүй.
 *
 * Бүх жишээ хэрэглэгчийн нууц үг: DEMO_PASSWORD орчны хувьсагч (заавал).
 *   php artisan db:seed --class=DemoSeeder --force
 *
 * Faker ашиглахгүй (production image-д байхгүй); тогтмол seed-тэй тул ажиллуулах бүрт ижил өгөгдөл гарна.
 */
class DemoSeeder extends Seeder
{
    /** Салбар => [код, байршил, [нэгж => [алба ...]]] */
    private const BRANCHES = [
        'Төв салбар' => ['TOV', 'Төв байр', [
            'Удирдлага' => ['Захиргаа', 'Санхүү', 'Хүний нөөц'],
            'Техникийн хэсэг' => ['Мэдээлэл технологи', 'Холбоо дохиолол', 'Хөдөлгөөний удирдлага'],
        ]],
        'Дархан салбар' => ['DRK', 'Дархан өртөө', [
            'Станц' => ['Станцын алба', 'Ачаа тээвэр'],
            'Удирдлага' => ['Захиргаа'],
        ]],
        'Замын-Үүд салбар' => ['ZU', 'Замын-Үүд өртөө', [
            'Станц' => ['Станцын алба', 'Ачаа тээвэр'],
        ]],
    ];

    /** [нэвтрэх нэр, эрх, салбар, нэгж, алба, овог, нэр, албан тушаал] */
    private const USERS = [
        ['admin', 'admin', 'Төв салбар', 'Техникийн хэсэг', 'Мэдээлэл технологи', 'Батболд', 'Энхжаргал', 'Ахлах инженер'],
        ['admin.darkhan', 'admin', 'Дархан салбар', 'Удирдлага', 'Захиргаа', 'Сүхбат', 'Ариунаа', 'МТ-ийн инженер'],
        ['admin.zamynuud', 'admin', 'Замын-Үүд салбар', 'Станц', 'Станцын алба', 'Жаргал', 'Төгөлдөр', 'МТ-ийн инженер'],

        ['ajiltan', 'employee', 'Төв салбар', 'Удирдлага', 'Захиргаа', 'Дорж', 'Ганбаатар', 'Албаны дарга'],
        ['oyunchimeg.ts', 'employee', 'Төв салбар', 'Удирдлага', 'Санхүү', 'Цэрэн', 'Оюунчимэг', 'Ерөнхий нягтлан бодогч'],
        ['solongo.m', 'employee', 'Төв салбар', 'Удирдлага', 'Санхүү', 'Мягмар', 'Солонго', 'Эдийн засагч'],
        ['sarangerel.n', 'employee', 'Төв салбар', 'Удирдлага', 'Хүний нөөц', 'Нямдорж', 'Сарангэрэл', 'Хүний нөөцийн мэргэжилтэн'],
        ['anudari.o', 'employee', 'Төв салбар', 'Удирдлага', 'Захиргаа', 'Очир', 'Анударь', 'Бичиг хэргийн ажилтан'],
        ['batzorig.p', 'employee', 'Төв салбар', 'Техникийн хэсэг', 'Мэдээлэл технологи', 'Пүрэв', 'Батзориг', 'Сүлжээний инженер'],
        ['temuulen.g', 'employee', 'Төв салбар', 'Техникийн хэсэг', 'Холбоо дохиолол', 'Гантулга', 'Тэмүүлэн', 'Холбооны инженер'],
        ['munkhbat.l', 'employee', 'Төв салбар', 'Техникийн хэсэг', 'Хөдөлгөөний удирдлага', 'Лхагва', 'Мөнхбат', 'Галт тэрэгний диспетчер'],
        ['tsogzolmaa.b', 'employee', 'Төв салбар', 'Техникийн хэсэг', 'Хөдөлгөөний удирдлага', 'Бат-Эрдэнэ', 'Цогзолмаа', 'Галт тэрэгний диспетчер'],

        ['bayarsaikhan.ch', 'employee', 'Дархан салбар', 'Станц', 'Станцын алба', 'Чулуун', 'Баярсайхан', 'Станцын дарга'],
        ['ganzorig.d', 'employee', 'Дархан салбар', 'Станц', 'Станцын алба', 'Дашдорж', 'Ганзориг', 'Станцын жижүүр'],
        ['nandin.t', 'employee', 'Дархан салбар', 'Станц', 'Ачаа тээвэр', 'Төмөр', 'Нандин', 'Тээврийн ажилтан'],
        ['uyanga.e', 'employee', 'Дархан салбар', 'Удирдлага', 'Захиргаа', 'Энхтөр', 'Уянга', 'Нярав'],

        ['khulan.b', 'employee', 'Замын-Үүд салбар', 'Станц', 'Станцын алба', 'Баяр', 'Хулан', 'Станцын дарга'],
        ['erdenebat.ts', 'employee', 'Замын-Үүд салбар', 'Станц', 'Ачаа тээвэр', 'Цогт', 'Эрдэнэбат', 'Ачаа хүлээн авагч'],
        ['misheel.g', 'employee', 'Замын-Үүд салбар', 'Станц', 'Станцын алба', 'Ганбат', 'Мишээл', 'Станцын жижүүр'],
    ];

    private const DESKTOPS = [
        ['Dell', 'OptiPlex 7090', 'Intel Core i5-10505', '8GB + 8GB', '512GB SSD', 2_850_000],
        ['HP', 'ProDesk 400 G7', 'Intel Core i5-10500', '8', '256GB SSD', 2_450_000],
        ['Lenovo', 'ThinkCentre M70q', 'Intel Core i5-12400T', '16', '512GB SSD', 2_980_000],
        ['Dell', 'OptiPlex 3080', 'Intel Core i3-10100', '8', '1TB HDD', 1_950_000],
        ['HP', 'EliteDesk 800 G6', 'Intel Core i7-10700', '16', '512GB SSD', 3_600_000],
    ];

    private const LAPTOPS = [
        ['Lenovo', 'ThinkPad E14 Gen 4', 'Intel Core i5-1235U', '16', '512GB SSD', 3_250_000],
        ['HP', 'ProBook 450 G8', 'Intel Core i5-1135G7', '8', '256GB SSD', 2_900_000],
        ['Dell', 'Latitude 5420', 'Intel Core i7-1185G7', '16', '512GB SSD', 3_900_000],
    ];

    private const MONITORS = [
        ['Dell', 'P2422H', 650_000], ['HP', 'E24 G4', 590_000], ['Samsung', 'S24R350', 420_000], ['LG', '24MK430H', 450_000],
    ];

    private const ACCESSORY_MODELS = [
        'mouse' => [['Logitech', 'M100'], ['Dell', 'MS116'], ['HP', 'X500']],
        'keyboard' => [['Logitech', 'K120'], ['Dell', 'KB216'], ['HP', 'K1500']],
    ];

    private string $password;

    /** Салбарын нэр => Branch */
    private array $branches = [];

    /** Нэвтрэх нэр => User */
    private array $users = [];

    private Carbon $clock;

    /** Бодит одоогийн цаг (хуурамч цагийг ашиглахаас өмнө) */
    private Carbon $realNow;

    private int $ip = 20;

    public function run(): void
    {
        if (Computer::withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoSeeder: төхөөрөмж бүртгэлтэй байна — жишээ өгөгдөл нэмэхгүй.');

            return;
        }

        $this->password = (string) env('DEMO_PASSWORD');
        if (mb_strlen($this->password) < 8) {
            throw new RuntimeException('DemoSeeder: DEMO_PASSWORD орчны хувьсагчийг 8-аас доошгүй тэмдэгтээр тохируулна уу.');
        }

        mt_srand(20261118);
        $this->realNow = Carbon::now();
        $this->clock = $this->realNow->copy()->subDays(90)->setTime(9, 0);

        try {
            DB::transaction(function () {
                $this->seedOrganization();
                $this->seedDevices();
                $this->seedRequests();
            });
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }

        $this->command?->info(sprintf(
            'DemoSeeder: %d салбар, %d хэрэглэгч, %d төхөөрөмж, %d хүсэлт үүслээ.',
            count($this->branches), count($this->users),
            Computer::withoutGlobalScopes()->count(), AssetRequest::count(),
        ));
    }

    // ---------- Салбар, нэгж, алба, хэрэглэгч ----------

    private function seedOrganization(): void
    {
        $this->tick(0);
        // Салбар, хэрэглэгчийг Супер админ үүсгэсэн мэтээр «Үйлдлийн түүх»-д
        if ($superadmin = User::query()->where('role', 'superadmin')->orderBy('id')->first()) {
            $this->actAs($superadmin);
        }
        foreach (self::BRANCHES as $name => [$code, $address]) {
            $this->branches[$name] = Branch::query()->where('code', $code)->first()
                ?? Branch::query()->firstOrCreate(['name' => $name], ['code' => $code, 'address' => $address]);
            $this->branches[$name]->fill(['address' => $this->branches[$name]->address ?: $address])->save();
        }

        foreach (self::USERS as [$username, $role, $branch, $unit, $department, $surname, $name, $position]) {
            $this->users[$username] = User::query()->firstOrCreate(['username' => $username], [
                'name' => $name,
                'surname' => $surname,
                'email' => $username.'@demo.example.com',
                'password' => $this->password,
                'role' => $role,
                'branch_id' => $this->branches[$branch]->id,
                'unit' => $unit,
                'department' => $department,
                'position' => $position,
                'is_active' => true,
                'must_change_password' => false,
            ]);
            // Хөгжүүлэлтийн орчинд UserSeeder-ийн «admin», «employee» салбаргүй байж болно: нууц үгийг нь хөндөхгүй, байгууллагын мэдээллийг нөхнө
            if (! $this->users[$username]->branch_id) {
                $this->users[$username]->update([
                    'surname' => $surname, 'branch_id' => $this->branches[$branch]->id,
                    'unit' => $unit, 'department' => $department, 'position' => $position,
                ]);
            }
        }
    }

    // ---------- Төхөөрөмж ----------

    private function seedDevices(): void
    {
        $employees = collect($this->users)->filter(fn (User $u) => $u->role !== 'superadmin');
        $kits = [];

        // Ажилтан бүрт процессор (mouse, keyboard-тэй иж бүрдэл) + дэлгэц; зарим нь нөүтбүктэй
        foreach ($employees as $username => $user) {
            $branch = $this->branchOf($user);
            $this->actAs($this->adminOf($branch));
            $this->tick(mt_rand(1, 3));

            $laptopUser = in_array($username, ['ajiltan', 'batzorig.p', 'admin', 'bayarsaikhan.ch', 'khulan.b'], true);
            $kits[$username] = $this->createKit($user, $branch);
            $this->createMonitor($user, $branch);
            if ($laptopUser) {
                $this->tick(0);
                $this->createLaptop($user, $branch);
            }
        }

        // Алба бүрийн хэвлэгч, UPS, сүлжээний төхөөрөмж, scanner
        foreach ($this->branches as $branchName => $branch) {
            $this->actAs($this->adminOf($branch));
            [, $place, $units] = self::BRANCHES[$branchName];
            foreach ($units as $unit => $departments) {
                foreach ($departments as $department) {
                    $this->tick(1);
                    $this->createShared($branch, $unit, $department, EquipmentCategory::Printer, ...$this->pick([
                        ['HP', 'LaserJet Pro M404dn', 1_150_000], ['Canon', 'i-SENSYS MF445dw', 1_480_000], ['Epson', 'L3250', 690_000],
                    ]));
                    $this->createShared($branch, $unit, $department, EquipmentCategory::Ups, ...$this->pick([
                        ['APC', 'Back-UPS BX1100', 480_000], ['Eaton', '5E 850i', 390_000],
                    ]));
                }
            }
            $this->tick(1);
            $first = array_key_first($units);
            $this->createShared($branch, $first, $units[$first][0], EquipmentCategory::Network, 'Cisco', 'Catalyst 2960-X 24', 3_200_000, 'Серверийн өрөө');
            $this->createShared($branch, $first, $units[$first][0], EquipmentCategory::Scanner, 'Canon', 'imageFORMULA DR-C225 II', 1_250_000);
        }

        // Агуулах: шинээр ирсэн, хариуцагчгүй төхөөрөмжүүд
        $tov = $this->branches['Төв салбар'];
        $this->actAs($this->adminOf($tov));
        $this->tick(2);
        foreach (range(1, 3) as $i) {
            $this->createStandalone($tov, EquipmentCategory::Processor, ComputerStatus::New, self::DESKTOPS[2], 'Агуулах');
        }
        foreach (range(1, 2) as $i) {
            $this->createStandalone($tov, EquipmentCategory::Monitor, ComputerStatus::New, self::MONITORS[0], 'Агуулах');
        }
        $this->createStandalone($tov, EquipmentCategory::Processor, ComputerStatus::InStorage, self::DESKTOPS[3], 'Агуулах', '2019-03-12');

        // Актласан (ашиглалтаас хассан) хуучин төхөөрөмж
        foreach (['2015-05-20', '2016-02-11'] as $date) {
            $this->createStandalone($tov, EquipmentCategory::Processor, ComputerStatus::Decommissioned,
                ['HP', 'Compaq Pro 6300', 'Intel Core i3-3220', '4', '500GB HDD', 1_200_000], 'Агуулах', $date);
        }
        $drk = $this->branches['Дархан салбар'];
        $this->actAs($this->adminOf($drk));
        $this->createStandalone($drk, EquipmentCategory::Monitor, ComputerStatus::Decommissioned, ['Samsung', 'S19C150', 250_000], 'Агуулах', '2014-09-01');

        // Шилжүүлэг, засвар, хүсэлт сүүлийн 4 долоо хоногт (хяналтын самбарт «сүүлийн үйлдэл» шинэ харагдана)
        $this->jumpTo(28);
        $this->seedTransfers();
        $this->jumpTo(22);
        $this->seedRepairs($kits);
        $this->seedAcknowledgements();
    }

    /** Процессор + mouse + keyboard (иж бүрдэл: -1, -2, -3) */
    private function createKit(User $user, Branch $branch): Computer
    {
        [$brand, $model, $cpu, $ram, $storage, $price] = $this->pick(self::DESKTOPS);
        $purchase = $this->purchaseDate();

        $pc = Computer::create([
            'category' => EquipmentCategory::Processor,
            'type' => ComputerType::Desktop,
            'brand' => $brand, 'model' => $model, 'cpu' => $cpu, 'ram_gb' => $ram, 'storage' => $storage,
            'os' => $purchase->year >= 2022 ? 'Windows 11 Pro' : 'Windows 10 Pro',
            'serial_number' => $this->serial($brand),
            'ip_address' => $this->nextIp($branch),
            'mac_address' => $this->mac(),
            'branch_id' => $branch->id,
            'location' => $this->location($branch),
            'assigned_user_id' => $user->id,
            'purchase_date' => $purchase, 'purchase_price' => $price,
            'warranty_until' => $purchase->copy()->addYears(3),
        ]);
        $this->assignedEvent($pc, $user);

        foreach (['mouse', 'keyboard'] as $category) {
            [$aBrand, $aModel] = $this->pick(self::ACCESSORY_MODELS[$category]);
            Computer::create([
                'category' => EquipmentCategory::from($category),
                'type' => ComputerType::Other,
                'brand' => $aBrand, 'model' => $aModel,
                'kit_parent_id' => $pc->id,
                'branch_id' => $branch->id,
                'location' => $pc->location,
                'assigned_user_id' => $user->id,
                'purchase_date' => $purchase, 'purchase_price' => $category === 'mouse' ? 25_000 : 45_000,
            ]);
        }

        return $pc->refresh();
    }

    private function createMonitor(User $user, Branch $branch): Computer
    {
        [$brand, $model, $price] = $this->pick(self::MONITORS);
        $purchase = $this->purchaseDate();
        // Цөөн хэдэн дэлгэцийн баталгаа удахгүй дуусна (хяналтын самбарын сануулгад)
        $warranty = mt_rand(1, 6) === 1 ? $this->realNow->copy()->addDays(mt_rand(5, 25))->startOfDay() : $purchase->copy()->addYears(3);

        $monitor = Computer::create([
            'category' => EquipmentCategory::Monitor,
            'type' => ComputerType::Other,
            'brand' => $brand, 'model' => $model,
            'specs' => '24", 1920×1080',
            'serial_number' => $this->serial($brand),
            'branch_id' => $branch->id,
            'location' => $this->location($branch),
            'assigned_user_id' => $user->id,
            'purchase_date' => $purchase, 'purchase_price' => $price,
            'warranty_until' => $warranty,
        ]);
        $this->assignedEvent($monitor, $user);

        return $monitor;
    }

    private function createLaptop(User $user, Branch $branch): Computer
    {
        [$brand, $model, $cpu, $ram, $storage, $price] = $this->pick(self::LAPTOPS);
        $purchase = Carbon::create(mt_rand(2022, 2025), mt_rand(1, 12), mt_rand(1, 28));

        $laptop = Computer::create([
            'category' => EquipmentCategory::Laptop,
            'type' => ComputerType::Laptop,
            'brand' => $brand, 'model' => $model, 'cpu' => $cpu, 'ram_gb' => $ram, 'storage' => $storage,
            'os' => 'Windows 11 Pro',
            'serial_number' => $this->serial($brand),
            'mac_address' => $this->mac(),
            'branch_id' => $branch->id,
            'assigned_user_id' => $user->id,
            'purchase_date' => $purchase, 'purchase_price' => $price,
            'warranty_until' => $purchase->copy()->addYears(2),
        ]);
        $this->assignedEvent($laptop, $user);

        return $laptop;
    }

    /** Албаны хамтын төхөөрөмж (хэвлэгч, UPS ...): хариуцагчгүй, «Ашиглагдаж байгаа» */
    private function createShared(Branch $branch, string $unit, string $department, EquipmentCategory $category,
        string $brand, string $model, int $price, ?string $location = null): Computer
    {
        $purchase = $this->purchaseDate();

        return Computer::create([
            'category' => $category,
            'type' => ComputerType::Other,
            'brand' => $brand, 'model' => $model,
            'serial_number' => $this->serial($brand),
            'ip_address' => in_array($category, [EquipmentCategory::Printer, EquipmentCategory::Network], true) ? $this->nextIp($branch) : null,
            'branch_id' => $branch->id,
            'unit' => $unit,
            'department' => $department,
            'location' => $location ?? $this->location($branch),
            'status' => ComputerStatus::Active,
            'purchase_date' => $purchase, 'purchase_price' => $price,
            'warranty_until' => $purchase->copy()->addYears(2),
        ]);
    }

    private function createStandalone(Branch $branch, EquipmentCategory $category, ComputerStatus $status, array $spec,
        string $location, ?string $purchased = null): Computer
    {
        $purchase = $purchased ? Carbon::parse($purchased) : Carbon::now()->subDays(mt_rand(3, 20))->startOfDay();
        $isPc = $category === EquipmentCategory::Processor;
        [$brand, $model] = $spec;
        $price = end($spec);

        return Computer::create([
            'category' => $category,
            'type' => $isPc ? ComputerType::Desktop : ComputerType::Other,
            'brand' => $brand, 'model' => $model,
            'cpu' => $isPc ? $spec[2] : null,
            'ram_gb' => $isPc ? $spec[3] : null,
            'storage' => $isPc ? $spec[4] : null,
            'os' => $isPc && $status !== ComputerStatus::Decommissioned ? 'Windows 11 Pro' : null,
            'serial_number' => $this->serial($brand),
            'branch_id' => $branch->id,
            'location' => $location,
            'status' => $status,
            'purchase_date' => $purchase, 'purchase_price' => $price,
            'warranty_until' => $purchase->copy()->addYears(3),
            'notes' => $status === ComputerStatus::Decommissioned ? 'Ашиглалтын хугацаа дууссан, эх хавтан эвдэрсэн. Актаар хассан.' : null,
        ]);
    }

    // ---------- Засвар ----------

    /** Засварын дараалал (3 төхөөрөмж) ба өмнө нь засагдсан түүх */
    private function seedRepairs(array $kits): void
    {
        $this->tick(3);

        // Өмнө засагдаж дууссан (түүхэнд харагдана)
        foreach (['oyunchimeg.ts' => ['Дискийн алдаа, систем удаан ачаалж байна.', 'HDD-г 512GB SSD-ээр сольж, Windows дахин суулгав.', 180_000],
                  'ganzorig.d' => ['Тэжээлийн блок дуугарч унтардаг.', 'Тэжээлийн блок солив.', 95_000]] as $username => [$problem, $fix, $cost]) {
            $pc = $kits[$username];
            $admin = $this->adminOf($this->branchOf($this->users[$username]));
            $this->actAs($admin);
            $pc->status = ComputerStatus::InRepair;
            $pc->save();
            $this->event($pc, $admin, 'repair', $problem);
            $this->tick(1);
            $pc->refresh();
            $pc->status = ComputerStatus::Active;
            $pc->save();
            $this->event($pc, $admin, 'repaired', $fix, $cost);
            $this->tick(1);
        }

        // Одоо засварт байгаа: эвдрэлийн мэдээлэлтэй, дараалалд
        $queue = [
            ['munkhbat.l', 'Асахгүй байна', 'Компьютер асахгүй байна, асаахад 3 удаа дуугарна.', RepairStage::WaitingParts, 'Санах ой (RAM) эвдэрсэн, сэлбэг захиалсан.', 120_000],
            ['nandin.t', 'Дүрс гарахгүй байна', 'Дэлгэц рүү дүрс гарахгүй, вентилятор хүчтэй шуугина.', RepairStage::Diagnosing, 'Видео гаралт, тэжээлийг шалгаж байна.', null],
            ['erdenebat.ts', 'Вирус орсон', 'Вирус орсон бололтой, файлууд нээгдэхгүй байна.', RepairStage::Queued, null, null],
        ];
        foreach ($queue as [$username, $title, $problem, $stage, $note, $cost]) {
            $user = $this->users[$username];
            $pc = $kits[$username];
            $admin = $this->adminOf($this->branchOf($user));

            $this->tick(1);
            $this->actAs($user);
            AssetRequest::create([
                'user_id' => $user->id, 'computer_id' => $pc->id, 'type' => 'issue',
                'subject' => $title, 'description' => $problem, 'status' => 'pending',
            ]);

            $this->tick(0);
            $this->actAs($admin);
            AssetRequest::query()->where('computer_id', $pc->id)->where('type', 'issue')
                ->update(['status' => 'in_progress', 'handled_by' => $admin->id, 'admin_note' => 'Засварт авлаа.']);
            $pc->refresh();
            $pc->status = ComputerStatus::InRepair;
            $pc->save();
            $this->event($pc, $admin, 'repair', $problem);

            if ($stage !== RepairStage::Queued) {
                $this->tick(1);
                $pc->refresh();
                $pc->repair_stage = $stage;
                $pc->save();
                $this->event($pc, $admin, 'repair_update', 'Явц: '.$stage->label().'. '.$note, $cost);
            }
        }
    }

    // ---------- Шилжүүлэг ----------

    private function seedTransfers(): void
    {
        $this->tick(2);
        $tov = $this->branches['Төв салбар'];
        $admin = $this->adminOf($tov);
        $this->actAs($admin);

        // Агуулахаас шинэ процессорыг ажилтанд олгосон
        $spare = Computer::query()->where('branch_id', $tov->id)->where('status', ComputerStatus::New)
            ->where('category', EquipmentCategory::Processor)->orderBy('id')->first();
        $to = $this->users['tsogzolmaa.b'];
        $spare->assigned_user_id = $to->id;
        $spare->ip_address = $this->nextIp($tov);
        $spare->location = $this->location($tov);
        $spare->save();
        $this->event($spare, $admin, 'assigned', 'Шинэ компьютер олгов.');
        ComputerTransfer::create([
            'computer_id' => $spare->id, 'from_label' => 'Агуулах', 'to_user_id' => $to->id, 'destination' => 'user',
            'issued_to_user_id' => $to->id, 'issued_by' => $admin->id, 'reason' => 'Хуучин компьютер удаан ажилладаг тул сольж олгов.',
            'status' => 'transferred', 'completed_at' => now(),
        ]);

        // Ажилтны дэлгэцийг агуулах руу буцаан авсан
        $this->tick(1);
        $from = $this->users['anudari.o'];
        $monitor = Computer::query()->where('assigned_user_id', $from->id)->where('category', EquipmentCategory::Monitor)->first();
        $monitor->assigned_user_id = null;
        $monitor->status = ComputerStatus::InStorage;
        $monitor->location = 'Агуулах';
        $monitor->save();
        $this->event($monitor, $admin, 'returned', 'Ажлын байр өөрчлөгдсөн тул буцаан авав.', null, $from->id);
        ComputerTransfer::create([
            'computer_id' => $monitor->id, 'from_user_id' => $from->id, 'destination' => 'storage',
            'issued_by' => $admin->id, 'reason' => 'Ажлын байр өөрчлөгдсөн.',
            'status' => 'transferred', 'completed_at' => now(),
        ]);
    }

    /** Ажилтнуудын ихэнх нь хүлээн авсан актаа баталгаажуулсан */
    private function seedAcknowledgements(): void
    {
        Computer::withoutGlobalScopes()->whereNotNull('assigned_user_id')->whereNull('kit_parent_id')
            ->orderBy('id')->get(['id', 'created_at'])
            ->each(function (Computer $c) {
                if (mt_rand(1, 10) <= 7) {
                    DB::table('computers')->where('id', $c->id)->update(['acknowledged_at' => $c->created_at->copy()->addHours(mt_rand(2, 30))]);
                }
            });
    }

    // ---------- Хүсэлт, захиалга ----------

    private function seedRequests(): void
    {
        $this->jumpTo(6);
        // Шинэ, хариу хүлээж буй эвдрэлийн мэдээлэл (Админы хяналтын самбарт)
        $this->tick(0);
        $user = $this->users['sarangerel.n'];
        $this->actAs($user);
        $monitor = Computer::query()->where('assigned_user_id', $user->id)->where('category', EquipmentCategory::Monitor)->first();
        AssetRequest::create([
            'user_id' => $user->id, 'computer_id' => $monitor->id, 'type' => 'issue',
            'subject' => 'Дэлгэц анивчиж байна', 'description' => 'Дэлгэц үе үе анивчиж, баруун талд нь босоо зураас гарч байна.',
            'status' => 'pending',
        ]);

        // Захиалгууд: хүлээгдэж буй, зөвшөөрсөн, хүлээлгэн өгсөн, татгалзсан
        $orders = [
            ['solongo.m', 'pending', 'Санхүүгийн программ ажиллуулахад хоёр дахь дэлгэц хэрэгтэй байна.', [['Дэлгэц', 1, 'pending']], null],
            ['bayarsaikhan.ch', 'pending', 'Станцын жижүүрийн өрөөнд тэжээл тасрахад компьютер унтарч байна.', [['UPS', 2, 'pending'], ['Keyboard', 1, 'pending']], null],
            ['temuulen.g', 'in_progress', 'Талбайн ажилд зөөврийн компьютер хэрэгтэй.', [['Нөүтбүк', 1, 'approved']], 'Худалдан авалтын жагсаалтад орсон.'],
            ['khulan.b', 'done', 'Хэвлэгчийн хор дууссан.', [['Хэвлэгчийн хор (HP 59A)', 2, 'delivered']], 'Хүлээлгэн өгсөн.'],
            ['uyanga.e', 'rejected', 'Гэрээсээ ажиллахад нөүтбүк авмаар байна.', [['Нөүтбүк', 1, 'rejected']], 'Зөвхөн ажлын байранд ашиглах компьютер олгоно.'],
        ];
        foreach ($orders as [$username, $status, $description, $items, $note]) {
            $this->tick(mt_rand(0, 1));
            $user = $this->users[$username];
            $admin = $this->adminOf($this->branchOf($user));
            $this->actAs($user);
            $request = AssetRequest::create([
                'user_id' => $user->id, 'type' => 'request',
                'subject' => AssetRequest::itemsSubject(array_map(fn ($i) => ['name' => $i[0], 'quantity' => $i[1]], $items)),
                'description' => $description, 'status' => 'pending',
            ]);
            foreach ($items as [$name, $quantity, $itemStatus]) {
                $request->items()->create([
                    'name' => $name, 'quantity' => $quantity, 'status' => $itemStatus,
                    'delivered_quantity' => $itemStatus === 'delivered' ? $quantity : 0,
                    'handled_by' => $itemStatus === 'pending' ? null : $admin->id,
                ]);
            }
            if ($status !== 'pending') {
                $this->tick(0);
                $request->update(['status' => $status, 'admin_note' => $note, 'handled_by' => $admin->id]);
            }
        }
    }

    // ---------- Туслах ----------

    /** Хуурамч цагийг урагшлуулна: бүртгэлийн дугаар (огноотой), түүхийн цаг 4 сарын турш тархана */
    private function tick(int $days): void
    {
        $this->clock->addDays($days)->addMinutes(mt_rand(7, 95));
        if ($this->clock->hour >= 18) {
            $this->clock->addDay()->setTime(9, mt_rand(0, 40));
        }
        // Бодит цагаас хэтрүүлэхгүй
        $limit = $this->realNow->copy()->subHours(1);
        if ($this->clock->greaterThan($limit)) {
            $this->clock = $limit->copy();
        }
        Carbon::setTestNow($this->clock->copy());
    }

    /** Хуурамч цагийг бодит цагаас $daysAgo өдрийн өмнөх өглөө рүү (ухрахгүй) */
    private function jumpTo(int $daysAgo): void
    {
        $target = $this->realNow->copy()->subDays($daysAgo)->setTime(9, mt_rand(0, 30));
        if ($target->greaterThan($this->clock)) {
            $this->clock = $target;
        }
        $this->tick(0);
    }

    private function actAs(User $user): void
    {
        Auth::setUser($user);
    }

    private function branchOf(User $user): Branch
    {
        return collect($this->branches)->first(fn (Branch $b) => (int) $b->id === (int) $user->branch_id);
    }

    private function adminOf(Branch $branch): User
    {
        return collect($this->users)->first(fn (User $u) => $u->role === 'admin' && (int) $u->branch_id === (int) $branch->id);
    }

    private function assignedEvent(Computer $computer, User $user): void
    {
        $this->event($computer, Auth::user(), 'assigned', null, null, $user->id);
    }

    private function event(Computer $computer, User $actor, string $type, ?string $note, ?int $cost = null, ?int $userId = null): void
    {
        ComputerEvent::create([
            'computer_id' => $computer->id,
            'actor_id' => $actor->id,
            'user_id' => $userId ?? $computer->assigned_user_id,
            'type' => $type,
            'note' => $note,
            'cost' => $cost,
        ]);
    }

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function purchaseDate(): Carbon
    {
        return Carbon::create(mt_rand(2019, 2025), mt_rand(1, 12), mt_rand(1, 28));
    }

    private function serial(string $brand): string
    {
        $prefix = ['Dell' => 'CN0', 'HP' => '8CG', 'Lenovo' => 'PF3', 'Samsung' => 'ZZB', 'LG' => '2LC'][$brand] ?? strtoupper(substr($brand, 0, 3));
        $chars = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';
        $s = $prefix;
        for ($i = 0; $i < 7; $i++) {
            $s .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $s;
    }

    private function mac(): string
    {
        $parts = ['00', '1A', '2B'];
        for ($i = 0; $i < 3; $i++) {
            $parts[] = sprintf('%02X', mt_rand(0, 255));
        }

        return implode(':', $parts);
    }

    private function nextIp(Branch $branch): string
    {
        $subnet = ['TOV' => 10, 'DRK' => 20, 'ZU' => 30][$branch->code] ?? 40;

        return '10.10.'.$subnet.'.'.($this->ip++);
    }

    private function location(Branch $branch): string
    {
        [, $place] = self::BRANCHES[$branch->name] ?? [null, $branch->name];

        return $place.', '.mt_rand(1, 4).'-р давхар, '.mt_rand(1, 4).sprintf('%02d', mt_rand(1, 20)).' тоот';
    }
}
