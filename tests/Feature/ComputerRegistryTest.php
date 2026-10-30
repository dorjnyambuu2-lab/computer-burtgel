<?php

namespace Tests\Feature;

use App\Models\Computer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputerRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $o = []): array
    {
        return array_merge([
            'inventory_number' => 'PC-0001', 'name' => 'PC-ACC-01', 'type' => 'desktop', 'status' => 'active',
            'brand' => 'Dell', 'ram_gb' => 16, 'ip_address' => '192.168.1.10', 'mac_address' => '00-1a-2b-3c-4d-5e',
            'purchase_date' => '2024-01-10', 'warranty_until' => '2027-01-10',
        ], $o);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/computers')->assertRedirect(route('login'));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

        $this->post('/login', ['login' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => $user->email, 'password' => 'secret123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pages_render(): void
    {
        $user = User::factory()->admin()->create();
        $computer = Computer::factory()->create();

        foreach (['/', '/computers', '/computers/create', "/computers/{$computer->id}", "/computers/{$computer->id}/edit"] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_can_create_update_and_delete_computer(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post('/computers', $this->valid())->assertSessionHasNoErrors()->assertRedirect();
        $computer = Computer::firstOrFail();
        $this->assertSame('00:1A:2B:3C:4D:5E', $computer->mac_address);

        $this->actingAs($user)->put("/computers/{$computer->id}", $this->valid(['name' => 'Renamed', 'status' => 'in_repair']))
            ->assertRedirect(route('computers.show', $computer));
        $this->assertDatabaseHas('computers', ['id' => $computer->id, 'name' => 'Renamed', 'status' => 'in_repair']);

        $this->actingAs($user)->delete("/computers/{$computer->id}")->assertRedirect(route('computers.index'));
        $this->assertSoftDeleted($computer);
    }

    public function test_storage_combines_ssd_hdd_m2_and_shows_total(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post('/computers', $this->valid(['storage_ssd' => '512', 'storage_hdd' => '1TB', 'storage_m2' => '256GB']))
            ->assertSessionHasNoErrors();
        $computer = Computer::firstOrFail();
        $this->assertSame('512GB SSD + 1TB HDD + 256GB M.2', $computer->storage);

        // Нийт хэмжээ, диск бүр төрлийн шошготой
        $this->actingAs($user)->get("/computers/{$computer->id}")->assertOk()
            ->assertSee('1792 GB')->assertSeeInOrder(['SSD', '512GB', 'HDD', '1TB', 'M.2', '256GB']);
        // Засахад M.2 өөрийн талбарт буцаж бөглөгдөнө
        $this->actingAs($user)->get("/computers/{$computer->id}/edit")->assertOk()
            ->assertSee('id="storage_m2" name="storage_m2" type="text" data-part="m2"', false)->assertSee('value="256GB"', false);
    }

    public function test_computer_form_can_add_new_department(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->get('/computers/create')->assertOk()->assertSee('＋ Шинэ алба нэмэх…');

        $this->actingAs($user)->post('/computers', $this->valid(['department' => 'Санхүүгийн алба']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', ['name' => 'Санхүүгийн алба', 'branch_id' => $user->branch_id]);

        // Сонгоод нэр бичээгүй бол алба хоосон
        $this->actingAs($user)->post('/computers', $this->valid(['inventory_number' => 'PC-0002', 'name' => 'PC-2', 'ip_address' => null, 'mac_address' => null, 'department' => '__new__']))->assertSessionHasNoErrors();
        $this->assertNull(Computer::where('name', 'PC-2')->value('department'));
    }

    public function test_new_device_registration_scenarios(): void
    {
        $admin = User::factory()->admin()->create();
        $form = fn (array $o = []) => array_merge(['type' => 'desktop', 'status' => 'new', 'purchase_date' => '2026-01-10', 'warranty_months' => '12'], $o);

        // Процессор + mouse, keyboard: иж бүрдэл (-1, -2, -3), диск, үнэ, баталгааг сараар бодно
        $this->actingAs($admin)->post('/computers', $form([
            'categories' => ['processor'], 'with_accessories' => ['mouse', 'keyboard'], 'name' => 'PC-KIT',
            'storage_ssd' => '512GB', 'storage_hdd' => '', 'storage_m2' => '', 'ram_gb' => 16, 'purchase_price' => '1,250,000₮',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $main = Computer::where('name', 'PC-KIT')->firstOrFail();
        $kit = Computer::where('kit_parent_id', $main->id)->get();
        $this->assertSame(['keyboard', 'mouse'], $kit->pluck('category.value')->sort()->values()->all());
        $this->assertStringEndsWith('-1', $main->inventory_number);
        $this->assertSame(['512GB SSD', 1250000.0, '2027-01-10', $admin->branch_id], [$main->storage, (float) $main->purchase_price, $main->warranty_until->toDateString(), $main->branch_id]);
        $this->assertTrue($kit->every(fn ($c) => $c->branch_id === $admin->branch_id));

        // Олон ангилал зэрэг: тус бүр тусдаа бүртгэл
        $before = Computer::count();
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['monitor', 'printer']]))->assertSessionHasNoErrors();
        $this->assertSame($before + 2, Computer::count());

        // Сүлжээний төхөөрөмж, зөөврийн компьютер
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['network'], 'name' => 'Switch-1', 'type' => 'other']))->assertSessionHasNoErrors();
        $this->assertSame('network', Computer::where('name', 'Switch-1')->first()->category->value);
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['laptop'], 'type' => 'laptop', 'name' => 'NB-1']))->assertSessionHasNoErrors();

        // «Бусад» нэргүй бол алдаа; дугаарыг гараар өгсөн ч автоматаар үүснэ
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['other']]))->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['ups'], 'name' => 'UPS-1', 'inventory_number' => 'MANUAL-1']))->assertSessionHasNoErrors();
        $this->assertNotSame('MANUAL-1', Computer::where('name', 'UPS-1')->value('inventory_number'));

        // Буруу утгууд
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['processor'], 'status' => 'xx', 'ram_gb' => 'abc', 'purchase_price' => '-5',
            'purchase_date' => '2026-01-10', 'warranty_until' => '2025-01-01', 'ip_address' => '999.1.1.1']))
            ->assertSessionHasErrors(['status', 'ram_gb', 'purchase_price', 'warranty_until', 'ip_address']);

        // «Хадгалаад дараагийнх»: ижил өгөгдлөөр шинэ форм руу
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['monitor'], 'name' => 'MON-NEXT', 'department' => 'МТ алба', 'save_and_new' => 1]))
            ->assertRedirect(route('computers.create', ['from' => Computer::where('name', 'MON-NEXT')->value('id')]));
        $this->actingAs($admin)->get(route('computers.create', ['from' => Computer::where('name', 'MON-NEXT')->value('id')]))->assertOk()->assertSee('МТ алба');

        // Админ өөр салбарт бүртгэж чадахгүй (үргэлж өөрийн салбар)
        $far = \App\Models\Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $this->actingAs($admin)->post('/computers', $form(['categories' => ['scanner'], 'name' => 'SC-1', 'branch_id' => $far->id]))->assertSessionHasNoErrors();
        $this->assertSame($admin->branch_id, Computer::where('name', 'SC-1')->value('branch_id'));

        // Супер админ салбар заавал сонгоно
        $super = User::factory()->superadmin()->create();
        $this->actingAs($super)->post('/computers', $form(['categories' => ['scanner'], 'name' => 'SC-2']))->assertSessionHasErrors('branch_id');
        $this->actingAs($super)->post('/computers', $form(['categories' => ['scanner'], 'name' => 'SC-2', 'branch_id' => $far->id]))->assertSessionHasNoErrors();
        $this->assertSame($far->id, Computer::withoutGlobalScopes()->where('name', 'SC-2')->value('branch_id'));

        // Ажилтан бүртгэх эрхгүй
        $this->actingAs(User::factory()->employee()->create())->post('/computers', $form(['categories' => ['scanner'], 'name' => 'SC-3']));
        $this->assertFalse(Computer::withoutGlobalScopes()->where('name', 'SC-3')->exists());
    }

    public function test_accessory_card_name_is_used(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['type' => 'desktop', 'status' => 'new', 'categories' => ['processor', 'other']];

        $this->actingAs($admin)->post('/computers', $base + ['items' => [['category' => 'processor'], ['category' => 'other', 'name' => 'Веб камер'], ['category' => 'other', 'name' => '']]])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Computer::where('name', 'Веб камер')->where('category', 'other')->exists());
        $this->assertTrue(Computer::where('name', 'Дагалдах хэрэгсэл 2')->exists()); // нэр бичээгүй бол ангиллын нэр

        // Ерөнхий нэртэй бол «Нэр · Веб камер»
        $this->actingAs($admin)->post('/computers', $base + ['name' => 'PC-7', 'items' => [['category' => 'processor'], ['category' => 'other', 'name' => 'Веб камер']]])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Computer::where('name', 'PC-7 · Веб камер')->exists());

        // Сүлжээний төхөөрөмж ч мөн адил
        $this->actingAs($admin)->post('/computers', ['type' => 'desktop', 'status' => 'new', 'categories' => ['processor', 'network'],
            'items' => [['category' => 'processor'], ['category' => 'network', 'name' => 'Router']]])->assertSessionHasNoErrors();
        $this->assertTrue(Computer::where('name', 'Router')->where('category', 'network')->exists());

        // Бичсэн нэр дараагийн удаа жагсаалтад гарна; «PC-7 · » угтвар, автомат нэр орохгүй
        $this->actingAs($admin)->post('/computers/multi', ['type' => 'desktop', 'status' => 'new', 'name' => 'PC-9',
            'items' => [['category' => 'processor'], ['category' => 'other', 'name' => 'Проектор']]])->assertSessionHasNoErrors();
        $page = $this->actingAs($admin)->get('/computers/create')->assertOk();
        $page->assertSee('<datalist id="sg-name-other">', false)->assertSee('<option value="Проектор">', false)
            ->assertDontSee('<option value="PC-9 · Проектор">', false)->assertDontSee('<option value="Дагалдах хэрэгсэл 2">', false)
            ->assertSee('<option value="Router">', false);
    }

    public function test_multi_registration_edge_cases(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['type' => 'desktop', 'status' => 'new'];

        // «＋ Шинэ алба нэмэх…» сонгоод нэр бичээгүй: алба хоосон («__new__» хадгалагдахгүй)
        $this->actingAs($admin)->post('/computers/multi', $base + ['department' => '__new__', 'items' => [['category' => 'processor'], ['category' => 'monitor']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Computer::where('department', '__new__')->count());
        Computer::query()->forceDelete();

        // 2 процессор + 2 дэлгэц: 2-р процессор дагалдах хэрэгсэл биш
        $this->actingAs($admin)->post('/computers/multi', $base + ['items' => [['category' => 'processor'], ['category' => 'processor'], ['category' => 'monitor'], ['category' => 'monitor']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Computer::where('category', 'processor')->whereNotNull('kit_parent_id')->count());
        $this->assertSame(2, Computer::where('category', 'monitor')->whereNotNull('kit_parent_id')->count());

        // Алдааны мессеж монголоор
        $this->actingAs($admin)->post('/computers/multi', $base + ['items' => [['category' => 'zzz'], ['category' => 'monitor']]])
            ->assertSessionHasErrors(['items.0.category' => 'Ангилал буруу байна.']);
        $this->actingAs(User::factory()->superadmin()->create())->post('/computers/multi', $base + ['items' => [['category' => 'monitor'], ['category' => 'printer']]])
            ->assertSessionHasErrors(['branch_id' => 'Салбар сонгоно уу.']);

        // Ганцаар + mouse: үндсэн компьютерийн автомат нэр шинэ дугаартайгаа таарна (-1)
        $this->actingAs($admin)->post('/computers', $base + ['categories' => ['processor'], 'with_accessories' => ['mouse']])->assertSessionHasNoErrors();
        $main = Computer::where('category', 'processor')->latest('id')->first();
        $this->assertSame('Процессор '.$main->inventory_number, $main->name);
    }

    public function test_new_holder_is_added_as_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->employee()->create(['name' => 'Болд', 'surname' => 'Бат']);

        // Байхгүй хүн: шинэ ажилтан үүсч, төхөөрөмж түүнд олгогдоно
        $this->actingAs($admin)->post('/computers', $this->valid(['holder_surname' => 'Дорж', 'holder_name' => 'Сараа', 'department' => 'МТ алба']))
            ->assertSessionHasNoErrors();
        $new = User::where('name', 'Сараа')->firstOrFail();
        $this->assertSame(['employee', 'Дорж', 'МТ алба', $admin->branch_id, true], [$new->role, $new->surname, $new->department, $new->branch_id, $new->is_active]);
        $this->assertNull($new->email);
        $this->assertSame(strtolower($new->code), $new->username);
        $this->assertSame($new->id, Computer::where('holder_name', 'Сараа')->value('assigned_user_id'));
        // Хариуцагчтай шинэ бүртгэл «Ашиглагдаж байгаа» төлөвтэй
        $this->assertSame('active', Computer::where('holder_name', 'Сараа')->first()->status->value);
        $this->actingAs($admin)->post('/computers', $this->valid(['ip_address' => '192.168.1.12', 'mac_address' => '00-1a-2b-3c-4d-60', 'status' => 'new']))
            ->assertSessionHasNoErrors();
        $this->assertSame('new', Computer::whereNull('assigned_user_id')->latest('id')->first()->status->value);

        // Байгаа хүн: шинээр үүсгэхгүй, түүнд олгоно
        $this->actingAs($admin)->post('/computers', $this->valid(['ip_address' => '192.168.1.11', 'mac_address' => '00-1a-2b-3c-4d-5f', 'holder_surname' => 'Бат', 'holder_name' => 'Болд']))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, User::where('name', 'Болд')->count());
        $this->assertSame($existing->id, Computer::where('holder_name', 'Болд')->value('assigned_user_id'));
    }

    public function test_device_counts_skip_mouse_and_keyboard(): void
    {
        $admin = User::factory()->admin()->create();
        $pc = Computer::factory()->create(['category' => 'processor', 'department' => 'МТ', 'branch_id' => $admin->branch_id]);
        Computer::factory()->create(['category' => 'monitor', 'department' => 'МТ', 'branch_id' => $admin->branch_id, 'kit_parent_id' => $pc->id]);
        Computer::factory()->create(['category' => 'mouse', 'department' => 'МТ', 'branch_id' => $admin->branch_id]);
        Computer::factory()->create(['category' => 'keyboard', 'department' => 'МТ', 'branch_id' => $admin->branch_id]);

        $this->assertSame(2, Computer::counted()->count());
        $this->actingAs($admin)->get('/computers')->assertOk()
            ->assertViewHas('deviceCount', 2)
            ->assertViewHas('departmentCounts', ['МТ' => 2])
            // Жагсаалтад зөвхөн процессор (иж бүрдлийн дэлгэц, mouse, keyboard харагдахгүй)
            ->assertViewHas('computers', fn ($p) => $p->pluck('id')->all() === [$pc->id]);
        // Ангиллаар шүүвэл mouse харагдана
        $this->actingAs($admin)->get('/computers?category=mouse')->assertOk()
            ->assertViewHas('computers', fn ($p) => $p->count() === 1);
    }

    public function test_kit_items_do_not_take_sequence_numbers(): void
    {
        $admin = User::factory()->admin()->create();
        // Процессор + mouse, keyboard хамт, дараа нь дахин нэг процессор: дэс дугаар #1, #2 (завсаргүй)
        $this->actingAs($admin)->post('/computers', $this->valid(['categories' => ['processor'], 'with_accessories' => ['mouse', 'keyboard']]))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/computers', $this->valid(['ip_address' => '192.168.1.11', 'mac_address' => '00-1a-2b-3c-4d-5f', 'categories' => ['processor'], 'with_accessories' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 2], Computer::whereNull('kit_parent_id')->orderBy('id')->pluck('sequence_number')->all());
        $kitItem = Computer::whereNotNull('kit_parent_id')->firstOrFail();
        $this->assertNull($kitItem->sequence_number);
        $this->assertSame('#1', $kitItem->sequenceLabel()); // үндсэн компьютерийн дугаар

        // Тусад нь бүртгэсэн mouse дугаар авахгүй, дараагийн дэлгэц #3 (завсаргүй)
        $mouse = Computer::factory()->create(['category' => 'mouse', 'branch_id' => $admin->branch_id]);
        $monitor = Computer::factory()->create(['category' => 'monitor', 'branch_id' => $admin->branch_id]);
        $this->assertNull($mouse->sequence_number);
        $this->assertSame(3, $monitor->sequence_number);
    }

    public function test_activity_log_shows_readable_collapsible_changes(): void
    {
        $super = User::factory()->superadmin()->create();
        $this->actingAs($super)->post('/computers', $this->valid(['status' => 'new', 'purchase_price' => '2500000', 'branch_id' => \Database\Factories\UserFactory::defaultBranchId()]))->assertSessionHasNoErrors();

        $this->actingAs($super)->get(route('activity.index'))->assertOk()
            ->assertSee('data-log-detail', false)             // мөр дээр дарахад нээгдэх дэлгэрэнгүй
            ->assertSee('Суурин компьютер')                 // type: desktop → шошго
            ->assertSee('2,500,000₮')
            ->assertSee('2024-01-10')
            ->assertDontSee('2024-01-10 00:00')
            ->assertDontSee('assigned_user_id');

        // Өдрөөр бүлэглэнэ; устгасан мөрөнд «Устгасан: Супер админ → —» биш тайлбар
        $this->actingAs($super)->delete('/computers/'.Computer::firstOrFail()->id);
        $this->actingAs($super)->get(route('activity.index'))->assertOk()
            ->assertSee('Өнөөдөр')
            ->assertSee('Устгасан хэсэг рүү шилжсэн');
    }

    public function test_new_holder_notifies_other_admins_and_message_stays(): void
    {
        $admin = User::factory()->admin()->create();
        $colleague = User::factory()->admin()->create();
        $farAdmin = User::factory()->admin()->create(['branch_id' => \App\Models\Branch::create(['name' => 'Дархан', 'code' => 'DRH'])->id]);
        $super = User::factory()->superadmin()->create();

        $this->actingAs($admin)->post('/computers', $this->valid(['holder_surname' => 'Бат', 'holder_name' => 'Энхээ']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'нууц үг тохируулж'))
            ->assertSessionMissing('success_autohide');

        $new = User::where('name', 'Энхээ')->firstOrFail();
        foreach ([$colleague, $super] as $u) {
            $n = $u->notifications()->firstOrFail();
            $this->assertStringContainsString('Бат Энхээ', $n->data['title']);
            $this->assertSame(route('users.edit', $new), $n->data['url']);
        }
        $this->assertSame(0, $admin->notifications()->count());    // үүсгэсэн хүнд биш
        $this->assertSame(0, $farAdmin->notifications()->count()); // өөр салбарын Админд биш

        // Засах форм ажилтнуудын жагсаалттай (санал болголт) нээгдэнэ
        $computer = Computer::firstOrFail();
        $this->actingAs($admin)->get("/computers/{$computer->id}/edit")->assertOk()->assertViewHas('holderCandidates');
    }

    public function test_dashboard_department_chart_shows_top_five_real_departments(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['А' => 6, 'Б' => 5, 'В' => 4, 'Г' => 3, 'Д' => 2, 'Е' => 1] as $dept => $n) {
            Computer::factory()->count($n)->create(['category' => 'processor', 'department' => $dept]);
        }
        Computer::factory()->count(9)->create(['category' => 'processor', 'department' => null]); // хамгийн олон ч хэлтэс биш

        $html = $this->actingAs($admin)->get('/')->assertOk()->assertSee('Хамгийн олон төхөөрөмжтэй 5 алба хэлтэс')->getContent();
        // Диаграмын шошгууд: А–Д (Е, «Бусад», «Хэлтэс оруулаагүй» биш)
        preg_match_all('/max-w-20 truncate" title="([^"]+)"/u', $html, $m);
        $this->assertSame(['А', 'Б', 'В', 'Г', 'Д'], $m[1]);
    }

    public function test_dashboard_recent_skips_accessories(): void
    {
        $admin = User::factory()->admin()->create();
        Computer::factory()->create(['category' => 'processor', 'name' => 'Үндсэн-PC']);
        Computer::factory()->create(['category' => 'mouse', 'name' => 'Ганц-Mouse']);

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Үндсэн-PC')->assertDontSee('Ганц-Mouse');
    }

    public function test_validation_rules(): void
    {
        $user = User::factory()->admin()->create();
        // Бүртгэлийн дугаар шинээр бүртгэхэд автоматаар үүсдэг тул давхардлыг серийн дугаараар шалгана
        Computer::factory()->create(['serial_number' => 'SN-DUP-1']);

        $this->actingAs($user)->post('/computers', $this->valid(['ip_address' => 'bad', 'mac_address' => 'zz', 'type' => 'x', 'serial_number' => 'SN-DUP-1']))
            ->assertSessionHasErrors(['serial_number', 'ip_address', 'mac_address', 'type']);

        $this->actingAs($user)->post('/computers', $this->valid(['inventory_number' => 'PC-9', 'warranty_until' => '2020-01-01']))
            ->assertSessionHasErrors('warranty_until');
    }

    public function test_search_and_filter(): void
    {
        $user = User::factory()->admin()->create();
        Computer::factory()->create(['name' => 'FINDME-PC', 'status' => 'active']);
        Computer::factory()->create(['name' => 'Other', 'status' => 'in_repair']);

        $this->actingAs($user)->get('/computers?q=FINDME')->assertSee('FINDME-PC')->assertDontSee('Other');
        $this->actingAs($user)->get('/computers?status=in_repair')->assertSee('Other')->assertDontSee('FINDME-PC');
    }

    public function test_csv_export(): void
    {
        $user = User::factory()->admin()->create();
        Computer::factory()->create(['name' => 'EXPORT-ME']);

        $response = $this->actingAs($user)->get('/computers/export');
        $response->assertOk();
        $this->assertStringContainsString('EXPORT-ME', $response->streamedContent());
    }
}
