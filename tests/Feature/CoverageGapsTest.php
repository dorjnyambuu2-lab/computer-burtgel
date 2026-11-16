<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Computer;
use App\Models\ComputerTransfer;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Өмнөх тестүүдэд хамрагдаагүй байсан маршрут, дүрмүүд:
 * нууц үгийн хүсэлт татгалзах, акт баталгаажуулах, салбар засах/устгах, IP/MAC давхардал,
 * шилжүүлгийн салбарын эрх, хэрэглэгч устгах, detect-script, «сүүлд харагдсан», Супер админы нууц үг сэргээх команд.
 */
class CoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->other = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
    }

    private function pc(array $o = []): Computer
    {
        return Computer::factory()->create($o + ['category' => 'processor', 'status' => 'active', 'ip_address' => null, 'mac_address' => null]);
    }

    private function form(array $o = []): array
    {
        return $o + ['branch_id' => \Database\Factories\UserFactory::defaultBranchId(), 'type' => 'desktop', 'status' => 'new', 'categories' => ['processor'], 'with_accessories' => [],
            'brand' => 'Dell', 'model' => 'OptiPlex 3000', 'ram_gb' => '8', 'purchase_date' => '2026-01-10', 'warranty_months' => '12'];
    }

    // ───────────── Нууц үг сэргээх хүсэлт татгалзах ─────────────

    public function test_password_request_reject_respects_branch_and_status(): void
    {
        $super = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();
        $farAdmin = User::factory()->admin()->create(['branch_id' => $this->other->id]);
        $emp = User::factory()->employee()->create();
        $oldHash = $emp->password;

        $req = PasswordResetRequest::create(['user_id' => $emp->id, 'status' => 'pending']);

        // Өөр салбарын Админ татгалзаж чадахгүй
        $this->actingAs($farAdmin)->post(route('password-requests.reject', $req))->assertForbidden();
        $this->assertSame('pending', $req->fresh()->status);

        // Өөрийн салбарын Админ татгалзана, нууц үг өөрчлөгдөхгүй
        $this->actingAs($admin)->from('/password-requests')->post(route('password-requests.reject', $req))
            ->assertRedirect('/password-requests')->assertSessionHas('success');
        $req->refresh();
        $this->assertSame(['rejected', $admin->id], [$req->status, $req->resolved_by]);
        $this->assertNotNull($req->resolved_at);
        $this->assertSame($oldHash, $emp->fresh()->password);

        // Шийдвэрлэгдсэн хүсэлтийг дахин дарахад алдааны хуудас биш, анхааруулгатай буцна; нууц үг өөрчлөгдөхгүй
        foreach (['password-requests.reject', 'password-requests.resolve'] as $route) {
            $this->actingAs($super)->from('/password-requests')->post(route($route, $req))
                ->assertRedirect('/password-requests')
                ->assertSessionHas('warning', fn ($m) => str_contains($m, 'аль хэдийн шийдвэрлэгдсэн') && str_contains($m, 'Татгалзсан'))
                ->assertSessionMissing('temporary_password');
        }
        $this->assertSame(['rejected', $admin->id], [$req->fresh()->status, $req->fresh()->resolved_by]);
        $this->assertSame($oldHash, $emp->fresh()->password);
    }

    public function test_double_click_on_resolve_shows_warning_not_debug_page(): void
    {
        config(['app.debug' => true]); // .env-д APP_DEBUG=true байх үеийн нөхцөл
        $super = User::factory()->superadmin()->create();
        $emp = User::factory()->employee()->create();
        $req = PasswordResetRequest::create(['user_id' => $emp->id, 'status' => 'pending']);

        $this->actingAs($super)->from('/password-requests')->post(route('password-requests.resolve', $req))->assertSessionHas('temporary_password');
        $issued = $emp->fresh()->password;

        // Хоёр дахь товшилт: түр нууц үг дахин солигдохгүй, stack trace хуудас гарахгүй
        $this->actingAs($super)->from('/password-requests')->post(route('password-requests.resolve', $req))
            ->assertRedirect('/password-requests')->assertSessionHas('warning');
        $this->assertSame($issued, $emp->fresh()->password);

        $this->actingAs($super)->get('/password-requests')->assertOk()->assertSee('аль хэдийн шийдвэрлэгдсэн');
    }

    public function test_admin_cannot_see_or_reject_admin_password_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $peer = User::factory()->admin()->create();
        $req = PasswordResetRequest::create(['user_id' => $peer->id, 'status' => 'pending']);

        // Админы хүсэлтийг зөвхөн Супер админ шийдвэрлэнэ
        $this->actingAs($admin)->post(route('password-requests.reject', $req))->assertForbidden();
        $this->actingAs($admin)->get('/password-requests')->assertOk()->assertDontSee($peer->username);
    }

    // ───────────── Ажилтан: акт баталгаажуулах (/my/acknowledge) ─────────────

    public function test_employee_acknowledges_only_own_devices(): void
    {
        $emp = User::factory()->employee()->create();
        $colleague = User::factory()->employee()->create();
        $mine = $this->pc(['assigned_user_id' => $emp->id]);
        $mine2 = Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'assigned_user_id' => $emp->id]);
        $theirs = $this->pc(['assigned_user_id' => $colleague->id]);

        $this->actingAs($emp)->from('/my/computers')->post(route('my.acknowledge'))
            ->assertRedirect('/my/computers')->assertSessionHas('success');

        $this->assertNotNull($mine->fresh()->acknowledged_at);
        $this->assertNotNull($mine2->fresh()->acknowledged_at);
        $this->assertNull($theirs->fresh()->acknowledged_at, 'Хамт ажиллагчийн төхөөрөмж баталгаажих ёсгүй');

        // Дахин дарахад анхны баталгаажуулсан огноо хэвээр үлдэнэ
        $first = $mine->fresh()->acknowledged_at;
        $this->travel(2)->days();
        $this->actingAs($emp)->post(route('my.acknowledge'));
        $this->assertEquals((string) $first, (string) $mine->fresh()->acknowledged_at);
    }

    // ───────────── Салбар: засах, устгах (зөвхөн Супер админ) ─────────────

    public function test_superadmin_updates_branch_with_validation_and_json(): void
    {
        $super = User::factory()->superadmin()->create();
        $erd = Branch::create(['name' => 'Эрдэнэт', 'code' => 'ERD']);

        // Код жижиг үсгээр ирсэн ч том үсэг болж хадгалагдана
        $this->actingAs($super)->put("/branches/{$erd->id}", ['name' => 'Эрдэнэт хот', 'code' => ' erd2 ', 'address' => 'Баян-Өндөр'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['Эрдэнэт хот', 'ERD2', 'Баян-Өндөр'], [$erd->fresh()->name, $erd->fresh()->code, $erd->fresh()->address]);

        // Өөр салбарын нэр, код давхардахгүй
        $this->actingAs($super)->put("/branches/{$erd->id}", ['name' => 'Дархан', 'code' => 'drh'])->assertSessionHasErrors(['name', 'code']);
        $this->assertSame('Эрдэнэт хот', $erd->fresh()->name);

        // Хэрэглэгчийн формоос fetch-ээр засахад JSON буцаана
        $this->actingAs($super)->putJson("/branches/{$erd->id}", ['name' => 'Орхон', 'code' => 'ORH'])
            ->assertOk()->assertJson(['id' => $erd->id, 'name' => 'Орхон', 'code' => 'ORH']);

        // Массив оруулсан ч 500 биш, валидацийн алдаа
        $this->actingAs($super)->putJson("/branches/{$erd->id}", ['name' => 'Орхон', 'code' => ['x']])->assertStatus(422);
    }

    public function test_branch_destroy_is_blocked_while_it_has_devices_or_users(): void
    {
        $super = User::factory()->superadmin()->create();
        $withPc = Branch::create(['name' => 'Төхөөрөмжтэй', 'code' => 'PC1']);
        $withUser = Branch::create(['name' => 'Хүнтэй', 'code' => 'US1']);
        $withTrashed = Branch::create(['name' => 'Архивтай', 'code' => 'TR1']);
        $empty = Branch::create(['name' => 'Хоосон', 'code' => 'EMP']);

        $this->pc(['branch_id' => $withPc->id]);
        User::factory()->employee()->create(['branch_id' => $withUser->id]);
        $this->pc(['branch_id' => $withTrashed->id])->delete(); // архивласан төхөөрөмж ч тоологдоно

        foreach ([$withPc, $withUser, $withTrashed] as $b) {
            $this->actingAs($super)->delete("/branches/{$b->id}")->assertSessionHasErrors('branch');
            $this->assertNotNull($b->fresh(), "«{$b->name}» салбар устах ёсгүй");
        }

        $this->actingAs($super)->delete("/branches/{$empty->id}")->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertNull($empty->fresh());
    }

    public function test_admin_cannot_manage_branches(): void
    {
        $admin = User::factory()->admin()->create();
        $b = Branch::create(['name' => 'Эрдэнэт', 'code' => 'ERD']);

        $this->actingAs($admin)->get('/branches')->assertForbidden();
        $this->actingAs($admin)->post('/branches', ['name' => 'Шинэ'])->assertForbidden();
        $this->actingAs($admin)->put("/branches/{$b->id}", ['name' => 'Өөр'])->assertForbidden();
        $this->actingAs($admin)->delete("/branches/{$b->id}")->assertForbidden();
        $this->assertSame('Эрдэнэт', $b->fresh()->name);
        $this->assertSame(0, Branch::where('name', 'Шинэ')->count());
    }

    // ───────────── IP, MAC давхардал (UniqueNetworkAddress) ─────────────

    public function test_ip_and_mac_must_be_unique_across_devices(): void
    {
        $super = User::factory()->superadmin()->create();
        $taken = $this->pc(['name' => 'Эзэмшигч', 'ip_address' => '192.168.1.50', 'mac_address' => '00:1A:2B:3C:4D:5E']);

        // Ижил IP
        $this->actingAs($super)->post('/computers', $this->form(['name' => 'Шинэ1', 'ip_address' => '192.168.1.50']))
            ->assertSessionHasErrors('ip_address');

        // Ижил MAC, гэхдээ жижиг үсэг, зураасаар бичсэн
        $this->actingAs($super)->post('/computers', $this->form(['name' => 'Шинэ2', 'mac_address' => '00-1a-2b-3c-4d-5e']))
            ->assertSessionHasErrors('mac_address');

        // Санамсаргүй (locally administered) MAC хүлээн авахгүй
        $this->actingAs($super)->post('/computers', $this->form(['name' => 'Шинэ3', 'mac_address' => '3A:11:22:33:44:55']))
            ->assertSessionHasErrors('mac_address');

        // Буруу хэлбэр
        $this->actingAs($super)->post('/computers', $this->form(['name' => 'Шинэ4', 'ip_address' => '999.1.1.1', 'mac_address' => 'ZZ:ZZ']))
            ->assertSessionHasErrors(['ip_address', 'mac_address']);

        $this->assertSame(0, Computer::where('name', 'like', 'Шинэ%')->count());

        // Өөрийнхөө бүртгэлийг засахад өөрийн IP, MAC саад болохгүй
        $this->actingAs($super)->put("/computers/{$taken->id}", ['name' => 'Эзэмшигч', 'type' => 'desktop', 'status' => 'active',
            'inventory_number' => $taken->inventory_number, 'branch_id' => $taken->branch_id,
            'ip_address' => '192.168.1.50', 'mac_address' => '00:1a:2b:3c:4d:5e',
        ])->assertSessionHasNoErrors();
        $this->assertSame('00:1A:2B:3C:4D:5E', $taken->fresh()->mac_address);

        // Устгасан (архивласан) төхөөрөмжийн хаягийг дахин ашиглаж болно
        $taken->delete();
        $this->actingAs($super)->post('/computers', $this->form(['name' => 'Шинэ5', 'ip_address' => '192.168.1.50', 'mac_address' => '00:1A:2B:3C:4D:5E']))
            ->assertSessionHasNoErrors();
        $this->assertSame('00:1A:2B:3C:4D:5E', Computer::where('name', 'Шинэ5')->value('mac_address'));
    }

    public function test_mac_helpers_normalize_and_detect_random(): void
    {
        $this->assertSame('AA:BB:CC:DD:EE:FF', Computer::normalizeMac(' aa-bb-cc-dd-ee-ff '));
        $this->assertNull(Computer::normalizeMac('FF:FF:FF:FF:FF:FF'), 'Broadcast хаяг хүчингүй');
        $this->assertNull(Computer::normalizeMac('not-a-mac'));
        $this->assertNull(Computer::normalizeMac(null));

        foreach (['02:00:00:00:00:01', '06:00:00:00:00:01', 'DA:00:00:00:00:01', 'fe-00-00-00-00-01'] as $mac) {
            $this->assertTrue(Computer::isRandomMac($mac), "$mac санамсаргүй MAC");
        }
        foreach (['00:1A:2B:3C:4D:5E', 'F4:8E:38:00:00:01', null, 'garbage'] as $mac) {
            $this->assertFalse(Computer::isRandomMac($mac), var_export($mac, true).' санамсаргүй биш');
        }
    }

    // ───────────── Шилжүүлэг: салбарын эрх, төлвийн дараалал ─────────────

    public function test_transfer_actions_respect_branch_and_state_order(): void
    {
        $admin = User::factory()->admin()->create();
        $farAdmin = User::factory()->admin()->create(['branch_id' => $this->other->id]);
        $from = User::factory()->employee()->create();
        $to = User::factory()->employee()->create();
        $farEmp = User::factory()->employee()->create(['branch_id' => $this->other->id]);
        $pc = $this->pc(['assigned_user_id' => $from->id]);

        $t = ComputerTransfer::create(['computer_id' => $pc->id, 'from_user_id' => $from->id, 'status' => 'pending']);

        // Өөр салбарын Админ хүрч чадахгүй
        $this->actingAs($farAdmin)->post(route('transfers.receive', $t))->assertForbidden();
        $this->actingAs($farAdmin)->post(route('transfers.reject', $t))->assertForbidden();
        $this->assertSame('pending', $t->fresh()->status);

        // Хүлээн авахаас өмнө олгох боломжгүй (алдааны хуудас биш, анхааруулга)
        $this->actingAs($admin)->from('/transfers')->post(route('transfers.issue', $t), ['user_id' => $to->id])
            ->assertRedirect('/transfers')->assertSessionHas('warning', fn ($m) => str_contains($m, 'эхлээд IT-д хүлээн авна'));
        $this->assertSame($from->id, $pc->fresh()->assigned_user_id);

        $this->actingAs($admin)->post(route('transfers.receive', $t))->assertSessionHasNoErrors();
        $this->assertSame('received', $t->fresh()->status);

        // Дахин хүлээн авах (давхар товшилт), татгалзах боломжгүй
        foreach (['transfers.receive', 'transfers.reject'] as $route) {
            $this->actingAs($admin)->from('/transfers')->post(route($route, $t))
                ->assertRedirect('/transfers')->assertSessionHas('warning', fn ($m) => str_contains($m, 'аль хэдийн шийдвэрлэгдсэн'));
        }
        $this->assertSame('received', $t->fresh()->status);

        // Өөр салбарын ажилтанд олгохгүй
        $this->actingAs($admin)->post(route('transfers.issue', $t), ['user_id' => $farEmp->id])->assertSessionHasErrors('user_id');
        $this->assertNull($pc->fresh()->assigned_user_id);

        $this->actingAs($admin)->post(route('transfers.issue', $t), ['user_id' => $to->id])->assertSessionHasNoErrors();
        $this->assertSame($to->id, $pc->fresh()->assigned_user_id);

        // Дууссан шилжүүлгийг дахин олгох боломжгүй
        $this->actingAs($admin)->post(route('transfers.issue', $t), ['user_id' => $from->id])->assertSessionHas('warning');
        $this->assertSame($to->id, $pc->fresh()->assigned_user_id);
        $this->assertSame('completed', $t->fresh()->status);
    }

    public function test_transfer_closed_by_archiving_warns_from_stale_page(): void
    {
        $admin = User::factory()->admin()->create();
        $from = User::factory()->employee()->create();
        $pc = $this->pc(['assigned_user_id' => $from->id]);
        $t = ComputerTransfer::create(['computer_id' => $pc->id, 'from_user_id' => $from->id, 'status' => 'pending']);

        // Архивлахад нээлттэй шилжүүлэг хаагдана (Computer::deleted)
        $pc->delete();
        $this->assertSame('rejected', $t->fresh()->status);

        // Өмнө нээсэн хуудаснаас «Хүлээн авах» дарвал алдааны хуудас биш, анхааруулга; юу ч өөрчлөгдөхгүй
        $this->actingAs($admin)->from('/transfers')->post(route('transfers.receive', $t))
            ->assertRedirect('/transfers')->assertSessionHas('warning', fn ($m) => str_contains($m, 'аль хэдийн шийдвэрлэгдсэн'));
        $this->assertSame('rejected', $t->fresh()->status);
        $this->assertSame($from->id, Computer::withTrashed()->find($pc->id)->assigned_user_id);
    }

    // ───────────── Утасны дэлгэц, гарчиг ─────────────

    public function test_device_page_layout_can_shrink_on_phones(): void
    {
        $admin = User::factory()->admin()->create();
        $pc = $this->pc(['status' => 'active']);
        Computer::factory()->create(['category' => 'mouse', 'status' => 'active', 'kit_parent_id' => $pc->id]);

        $html = $this->actingAs($admin)->get("/computers/{$pc->id}")->assertOk()->getContent();
        // Үндсэн grid minmax(0,1fr) баганатай; иж бүрдлийн ангилал утсан дээр нуугдана
        $this->assertStringContainsString('class="grid grid-cols-1 gap-4 lg:grid-cols-3"', $html);
        $this->assertStringContainsString('class="hidden w-24 shrink-0 text-xs text-slate-500 sm:block"', $html);
    }

    public function test_password_change_page_has_its_own_title(): void
    {
        $emp = User::factory()->employee()->create(['must_change_password' => true]);

        $this->actingAs($emp)->get('/my/password')->assertOk()
            ->assertSee('<title>Нууц үг солих · ', false)
            ->assertDontSee('<title>Хянах самбар', false);
    }

    public function test_direct_transfer_rejects_in_repair_and_same_holder(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $repairing = $this->pc(['assigned_user_id' => $emp->id, 'status' => 'in_repair']);
        $held = $this->pc(['assigned_user_id' => $emp->id]);

        $this->actingAs($admin)->post('/transfers', ['computer_id' => $repairing->id, 'to_user_id' => $other->id])
            ->assertSessionHasErrors('computer_id');
        $this->actingAs($admin)->post('/transfers', ['computer_id' => $held->id, 'to_user_id' => $emp->id])
            ->assertSessionHasErrors('to_user_id');
        $this->assertSame(0, ComputerTransfer::count());

        $this->actingAs($admin)->post('/transfers', ['computer_id' => $held->id, 'to_user_id' => $other->id, 'reason' => 'Ажил солисон'])
            ->assertSessionHasNoErrors();
        $this->assertSame($other->id, $held->fresh()->assigned_user_id);
        $this->assertSame(1, ComputerTransfer::where('computer_id', $held->id)->where('status', 'transferred')->count());
    }

    // ───────────── Хэрэглэгч устгах ─────────────

    public function test_user_delete_permissions_and_held_device_guard(): void
    {
        $super = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();
        $own = User::factory()->employee()->create();
        $far = User::factory()->employee()->create(['branch_id' => $this->other->id]);
        $holder = User::factory()->employee()->create();
        $this->pc(['assigned_user_id' => $holder->id]);

        // Админ: өөр салбарын ажилтан, өөр админ, өөрийгөө устгаж чадахгүй
        $this->actingAs($admin)->delete("/users/{$far->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$super->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();

        // Төхөөрөмжтэй ажилтныг устгахгүй
        $this->actingAs($admin)->delete("/users/{$holder->id}")->assertSessionHas('warning');
        $this->assertNotNull($holder->fresh());

        // Өөрийн салбарын ажилтныг устгана
        $this->actingAs($admin)->delete("/users/{$own->id}")->assertRedirect(route('users.index'));
        $this->assertNull($own->fresh());

        // Супер админ өөр салбарын ажилтныг устгаж чадна, өөрийгөө чадахгүй
        $this->actingAs($super)->delete("/users/{$far->id}")->assertRedirect(route('users.index'));
        $this->assertNull($far->fresh());
        $this->actingAs($super)->delete("/users/{$super->id}")->assertForbidden();
    }

    // ───────────── Detect script (.bat) ─────────────

    public function test_detect_script_downloads_bat_for_staff_only(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();

        $res = $this->actingAs($admin)->get('/computers/detect-script')->assertOk();
        $this->assertStringContainsString('computer-burtgel.bat', $res->headers->get('Content-Disposition'));
        $body = $res->getContent();
        $this->assertStringStartsWith("@echo off\r\n", $body);
        $this->assertStringContainsString('/computers/create', $body);
        $this->assertStringNotContainsString('__CREATE_URL__', $body);
        $this->assertStringNotContainsString("\r\r\n", $body, 'CRLF давхардах ёсгүй');

        $this->assertNotSame(200, $this->actingAs($emp)->get('/computers/detect-script')->getStatusCode());
    }

    // ───────────── «Сүүлд харагдсан» (TrackLastSeen) ─────────────

    public function test_requests_mark_user_online_until_idle(): void
    {
        $emp = User::factory()->employee()->create(['is_online' => false, 'last_seen_at' => null]);

        $this->actingAs($emp)->get('/my/computers')->assertOk();
        $fresh = $emp->fresh();
        $this->assertTrue($fresh->is_online);
        $this->assertNotNull($fresh->last_seen_at);
        $this->assertTrue($fresh->isOnline());

        // 10 минут идэвхгүй бол онлайн биш
        $this->travel(30)->minutes();
        $this->assertFalse($emp->fresh()->isOnline());
    }

    // ───────────── Супер админы нууц үг сэргээх команд ─────────────

    public function test_superadmin_reset_password_command(): void
    {
        $super = User::factory()->superadmin()->create(['username' => 'root', 'must_change_password' => true, 'is_active' => false]);

        // Таарахгүй давталт
        $this->artisan('superadmin:reset-password', ['username' => 'root'])
            ->expectsQuestion('Шинэ нууц үг (8+ тэмдэгт)', 'NewPass123')
            ->expectsQuestion('Шинэ нууц үг давтах', 'Other1234')
            ->assertExitCode(1);
        $this->assertFalse(Hash::check('NewPass123', $super->fresh()->password));

        // Богино нууц үг
        $this->artisan('superadmin:reset-password', ['username' => 'root'])
            ->expectsQuestion('Шинэ нууц үг (8+ тэмдэгт)', 'short')
            ->expectsQuestion('Шинэ нууц үг давтах', 'short')
            ->assertExitCode(1);

        // Байхгүй хэрэглэгч, эсвэл Супер админ биш хэрэглэгч
        $emp = User::factory()->employee()->create(['username' => 'worker']);
        $this->artisan('superadmin:reset-password', ['username' => 'nobody'])->assertExitCode(1);
        $this->artisan('superadmin:reset-password', ['username' => 'worker'])->assertExitCode(1);

        // Амжилттай: нууц үг солигдож, идэвхжиж, солих шаардлага арилна
        $this->artisan('superadmin:reset-password', ['username' => 'root'])
            ->expectsQuestion('Шинэ нууц үг (8+ тэмдэгт)', 'NewPass123')
            ->expectsQuestion('Шинэ нууц үг давтах', 'NewPass123')
            ->assertExitCode(0);
        $super->refresh();
        $this->assertTrue(Hash::check('NewPass123', $super->password));
        $this->assertFalse($super->must_change_password);
        $this->assertTrue($super->is_active);
        $this->assertTrue(Hash::check('password', $emp->fresh()->password), 'Ажилтны нууц үг хөндөгдөх ёсгүй');

        // Шинэ нууц үгээр нэвтэрнэ
        $this->post('/login', ['login' => 'root', 'password' => 'NewPass123'])->assertRedirect();
        $this->assertAuthenticatedAs($super);
    }
}
