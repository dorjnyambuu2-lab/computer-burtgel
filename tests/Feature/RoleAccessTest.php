<?php

namespace Tests\Feature;

use App\Models\AssetRequest;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Гурван эрхээр (Супер админ, Админ, Ажилтан) системийн хуудас, үйлдлийг шалгана:
 * юу харагдах, юу хориотой, салбарын хязгаарлалт, гол урсгалууд.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Удирдлагын хуудаснууд (Супер админ, Админд нээлттэй) */
    private const MANAGER_PAGES = [
        '/', '/computers', '/computers/create', '/computers/trash', '/computers/bulk-assign', '/transfers',
        '/requests', '/repairs', '/activity', '/users', '/users/create', '/password-requests',
        '/profile', '/my/notifications',
    ];

    private Branch $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->other = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
    }

    private function pc(array $o = []): Computer
    {
        return Computer::factory()->create(['category' => 'processor', 'status' => 'active'] + $o);
    }

    private function form(array $o = []): array
    {
        return $o + ['type' => 'desktop', 'status' => 'new', 'categories' => ['processor'], 'with_accessories' => [],
            'brand' => 'Dell', 'model' => 'OptiPlex 3000', 'ram_gb' => '8GB + 8GB', 'purchase_date' => '2026-01-10', 'warranty_months' => '12'];
    }

    // ───────────── Супер админ ─────────────

    public function test_superadmin_opens_every_page(): void
    {
        $super = User::factory()->superadmin()->create();
        $c = $this->pc();

        foreach ([...self::MANAGER_PAGES, '/branches', "/computers/{$c->id}", "/computers/{$c->id}/edit", "/computers/{$c->id}/label"] as $url) {
            $this->actingAs($super)->get($url)->assertOk();
        }
    }

    public function test_superadmin_sees_all_branches_and_registers_in_any(): void
    {
        $super = User::factory()->superadmin()->create();
        $own = $this->pc(['name' => 'Төв-PC']);
        $far = $this->pc(['name' => 'Дархан-PC', 'branch_id' => $this->other->id]);

        $this->actingAs($super)->get('/computers')->assertOk()->assertSee('Төв-PC')->assertSee('Дархан-PC');
        $this->actingAs($super)->get("/computers/{$far->id}")->assertOk();

        $this->actingAs($super)->post('/computers', $this->form(['branch_id' => $this->other->id, 'name' => 'Шинэ-Дархан']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $new = Computer::where('name', 'Шинэ-Дархан')->firstOrFail();
        $this->assertSame($this->other->id, $new->branch_id);
        $this->assertSame(16, $new->ram_gb);
        $this->assertSame('8GB + 8GB', $new->ram_modules);
        $this->assertNotNull($own->fresh());
    }

    public function test_superadmin_creates_admin_and_branch(): void
    {
        $super = User::factory()->superadmin()->create();

        $this->actingAs($super)->post('/branches', ['name' => 'Эрдэнэт', 'code' => 'ERD'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', ['code' => 'ERD']);

        $this->actingAs($super)->post('/users', [
            'name' => 'Шинэ админ', 'username' => 'newadmin', 'email' => 'newadmin@example.mn', 'role' => 'admin',
            'branch_id' => $this->other->id, 'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'newadmin', 'role' => 'admin', 'branch_id' => $this->other->id]);
    }

    public function test_superadmin_force_deletes_with_password_only(): void
    {
        $super = User::factory()->superadmin()->create();
        $c = $this->pc();
        $c->delete();

        $this->actingAs($super)->delete(route('computers.force-delete', $c->id), ['password' => 'wrong'])
            ->assertSessionHasErrors('password', null, 'forceDelete');
        $this->assertSoftDeleted($c);

        $this->actingAs($super)->delete(route('computers.force-delete', $c->id), ['password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('computers', ['id' => $c->id]);
    }

    public function test_superadmin_is_never_offered_as_holder(): void
    {
        $super = User::factory()->superadmin()->create(['name' => 'Супер Хүн']);
        $c = $this->pc(['assigned_user_id' => null]);

        $this->actingAs($super)->get("/computers/{$c->id}")->assertOk()->assertDontSee('Супер Хүн (');
        $this->actingAs($super)->post(route('computers.assign', $c), ['user_id' => $super->id])->assertSessionHasErrors('user_id');
        $this->assertNull($c->fresh()->assigned_user_id);
    }

    public function test_kit_items_follow_main_computer_on_delete_restore_and_force_delete(): void
    {
        $super = User::factory()->superadmin()->create();
        $main = $this->pc();
        $monitor = Computer::factory()->create(['category' => 'monitor', 'kit_parent_id' => $main->id]);
        $mouse = Computer::factory()->create(['category' => 'mouse', 'kit_parent_id' => $main->id]);
        $earlier = Computer::factory()->create(['category' => 'keyboard', 'kit_parent_id' => $main->id]);
        $this->travel(-1)->days();
        $earlier->delete(); // өмнө нь тусад нь устгасан: үндсэнийг сэргээхэд хамт сэргэхгүй
        $this->travelBack();

        // Архивлахад хэрэгслүүд хамт, тоонд үлдэхгүй
        $this->actingAs($super)->delete("/computers/{$main->id}")->assertRedirect(route('computers.index'));
        $this->assertSoftDeleted($monitor);
        $this->assertSoftDeleted($mouse);
        $this->assertSame($super->id, $monitor->fresh()->deleted_by);
        $this->assertSame(0, Computer::counted()->count());

        // Сэргээхэд хамт архивлагдсан нь сэргэнэ, өмнө нь устгасан нь үгүй
        $this->actingAs($super)->post(route('computers.restore', $main->id))->assertSessionHasNoErrors();
        $this->assertNull($monitor->fresh()->deleted_at);
        $this->assertNull($mouse->fresh()->deleted_at);
        $this->assertSoftDeleted($earlier);

        // Бүрмөсөн устгахад бүгд устна, эзэнгүй хэрэгсэл үлдэхгүй
        $main->delete();
        $this->actingAs($super)->delete(route('computers.force-delete', $main->id), ['password' => 'password'])->assertSessionHasNoErrors();
        foreach ([$main, $monitor, $mouse, $earlier] as $c) {
            $this->assertDatabaseMissing('computers', ['id' => $c->id]);
        }
    }

    // ───────────── Админ ─────────────

    public function test_admin_opens_manager_pages_but_not_superadmin_ones(): void
    {
        $admin = User::factory()->admin()->create();
        $c = $this->pc();

        foreach ([...self::MANAGER_PAGES, "/computers/{$c->id}", "/computers/{$c->id}/edit"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get('/branches')->assertForbidden();
        $this->actingAs($admin)->delete('/activity', ['password' => 'password'])->assertForbidden();

        $c->delete();
        $this->actingAs($admin)->delete(route('computers.force-delete', $c->id), ['password' => 'password'])->assertForbidden();
        $this->assertSoftDeleted($c);
    }

    public function test_admin_is_limited_to_own_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $this->pc(['name' => 'Төв-PC']);
        $far = $this->pc(['name' => 'Дархан-PC', 'branch_id' => $this->other->id]);

        $this->actingAs($admin)->get('/computers')->assertOk()->assertSee('Төв-PC')->assertDontSee('Дархан-PC');
        $this->actingAs($admin)->get("/computers/{$far->id}")->assertNotFound();
        $this->actingAs($admin)->put("/computers/{$far->id}", ['name' => 'Хакердсан'])->assertNotFound();
        $this->actingAs($admin)->delete("/computers/{$far->id}")->assertNotFound();
        $this->assertSame('Дархан-PC', $far->fresh()->name);

        // Өөр салбар сонгосон ч өөрийн салбарт бүртгэгдэнэ
        $this->actingAs($admin)->post('/computers', $this->form(['branch_id' => $this->other->id, 'name' => 'Админы-PC']))
            ->assertSessionHasNoErrors();
        $this->assertSame($admin->branch_id, Computer::where('name', 'Админы-PC')->value('branch_id'));
    }

    public function test_admin_manages_only_employees_of_own_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $farEmployee = User::factory()->employee()->create(['branch_id' => $this->other->id]);

        $user = fn (array $o) => $o + ['name' => 'Ажилтан', 'email' => null, 'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1];

        $this->actingAs($admin)->post('/users', $user(['username' => 'emp1', 'role' => 'employee']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['username' => 'emp1', 'role' => 'employee', 'branch_id' => $admin->branch_id]);

        // Админ, Супер админ эрх олгож болохгүй
        $this->actingAs($admin)->post('/users', $user(['username' => 'adm2', 'role' => 'admin']))->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'adm2']);

        // Өөр салбарын ажилтныг засах эрхгүй
        $this->actingAs($admin)->get("/users/{$farEmployee->id}/edit")->assertForbidden();
    }

    public function test_admin_assigns_only_employees_and_status_follows(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create(['name' => 'Өөр Админ']);
        $employee = User::factory()->employee()->create(['name' => 'Цэцэг']);
        $c = $this->pc(['status' => 'new', 'assigned_user_id' => null]);

        $this->actingAs($admin)->get("/computers/{$c->id}")->assertOk()
            ->assertSee('Цэцэг (', false)->assertDontSee('Өөр Админ (', false);

        $this->actingAs($admin)->post(route('computers.assign', $c), ['user_id' => $employee->id])->assertSessionHasNoErrors();
        $this->assertSame($employee->id, $c->fresh()->assigned_user_id);
        $this->assertSame('active', $c->fresh()->status->value);

        $this->actingAs($admin)->post(route('computers.return', $c))->assertSessionHasNoErrors();
        $this->assertNull($c->fresh()->assigned_user_id);
        $this->assertSame('in_storage', $c->fresh()->status->value);
        $this->assertNotNull($otherAdmin);
    }

    public function test_admin_repair_flow_from_employee_request(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $c = $this->pc(['assigned_user_id' => $employee->id]);

        $this->actingAs($employee)->post('/my/requests', ['type' => 'issue', 'computer_id' => $c->id, 'description' => 'Асахгүй байна'])
            ->assertSessionHasNoErrors();
        $req = AssetRequest::firstOrFail();

        // Ажилтны хүсэлт → Засвар
        $this->actingAs($admin)->get('/requests')->assertOk()->assertSee('Асахгүй байна');
        $this->actingAs($admin)->post(route('requests.repair', $req))->assertRedirect(route('repairs.index'));
        $this->assertSame('in_repair', $c->fresh()->status->value);
        $this->assertSame('in_progress', $req->fresh()->status);

        // Засвар дуусахад хариуцагчтай тул «Ашиглагдаж байгаа», хүсэлт «Дууссан»
        $this->actingAs($admin)->patch(route('repairs.update', $c), ['stage' => 'done', 'cost' => '150,000'])->assertSessionHasNoErrors();
        $this->assertSame('active', $c->fresh()->status->value);
        $this->assertSame('done', $req->fresh()->status);
    }

    public function test_transfer_page_device_picker_and_transfer(): void
    {
        $admin = User::factory()->admin()->create();
        $from = User::factory()->employee()->create(['name' => 'Болд']);
        $to = User::factory()->employee()->create(['name' => 'Сараа']);
        $pc = $this->pc(['assigned_user_id' => $from->id, 'name' => 'Шилжих-PC', 'location' => '3 давхар']);
        Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'kit_parent_id' => $pc->id, 'assigned_user_id' => $from->id]);

        // Сонгох цонх (хүснэгтийн өгөгдөл, шүүлтүүр) зурагдаж, ?computer=-оор урьдчилан сонгогдоно
        $this->actingAs($admin)->get('/transfers?computer='.$pc->id)->assertOk()
            ->assertSee('id="dlg-device"', false)
            ->assertSee(trim(json_encode('Шилжих-PC'), '"'), false) // хүснэгтийн өгөгдөл (JSON)
            ->assertSee('3 давхар')                                  // байршлын шүүлтүүр
            ->assertViewHas('devices', fn ($d) => $d->count() === 2);

        $this->actingAs($admin)->post('/transfers', ['computer_id' => $pc->id, 'to_user_id' => $to->id, 'reason' => 'Хэлтэс солив'])
            ->assertSessionHasNoErrors();
        $this->assertSame($to->id, $pc->fresh()->assigned_user_id);
        $this->assertSame(2, Computer::where('assigned_user_id', $to->id)->count()); // иж бүрдлээрээ
    }

    // ───────────── Ажилтан ─────────────

    public function test_employee_sees_only_own_pages(): void
    {
        $employee = User::factory()->employee()->create();
        $mine = $this->pc(['assigned_user_id' => $employee->id, 'name' => 'Миний-PC']);
        $theirs = $this->pc(['name' => 'Бусдын-PC']);

        foreach (['/', '/my/computers', "/my/computers/{$mine->id}", '/profile', '/my/notifications', '/my/password'] as $url) {
            $this->actingAs($employee)->get($url)->assertOk();
        }
        $this->actingAs($employee)->get('/my/computers')->assertSee('Миний-PC')->assertDontSee('Бусдын-PC');
        $this->actingAs($employee)->get("/my/computers/{$theirs->id}")->assertNotFound();

        // Удирдлагын хуудас руу орохгүй (нүүр рүү буцаана)
        foreach (['/computers', '/computers/create', "/computers/{$mine->id}", '/users', '/activity', '/repairs', '/requests', '/transfers', '/branches', '/computers/trash'] as $url) {
            $this->actingAs($employee)->get($url)->assertRedirect('/');
        }
    }

    public function test_employee_cannot_change_registry(): void
    {
        $employee = User::factory()->employee()->create();
        $mine = $this->pc(['assigned_user_id' => $employee->id, 'name' => 'Миний-PC']);

        $this->actingAs($employee)->post('/computers', $this->form(['name' => 'Ажилтны-PC']))->assertRedirect('/');
        $this->actingAs($employee)->put("/computers/{$mine->id}", ['name' => 'Өөрчилсөн'])->assertRedirect('/');
        $this->actingAs($employee)->delete("/computers/{$mine->id}")->assertRedirect('/');
        $this->actingAs($employee)->post('/users', ['name' => 'x', 'username' => 'x', 'role' => 'admin'])->assertRedirect('/');

        $this->assertDatabaseMissing('computers', ['name' => 'Ажилтны-PC']);
        $this->assertSame('Миний-PC', $mine->fresh()->name);
        $this->assertNull($mine->fresh()->deleted_at);
        $this->assertDatabaseMissing('users', ['username' => 'x']);
    }

    public function test_employee_requests_and_acknowledges_only_own_devices(): void
    {
        $employee = User::factory()->employee()->create();
        $mine = $this->pc(['assigned_user_id' => $employee->id, 'acknowledged_at' => null]);
        $theirs = $this->pc();

        $this->actingAs($employee)->post('/my/requests', ['type' => 'issue', 'computer_id' => $theirs->id, 'description' => 'x'])->assertForbidden();
        $this->actingAs($employee)->post('/my/requests', ['type' => 'request', 'subject' => 'Дэлгэц ×2', 'description' => 'Хоёр дэлгэц хэрэгтэй'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asset_requests', ['user_id' => $employee->id, 'type' => 'request']);

        $this->actingAs($employee)->post(route('my.computers.acknowledge', $theirs->id))->assertNotFound();
        $this->actingAs($employee)->post(route('my.computers.acknowledge', $mine->id))->assertSessionHasNoErrors();
        $this->assertNotNull($mine->fresh()->acknowledged_at);
    }

    public function test_inactive_user_and_forced_password_change(): void
    {
        $inactive = User::factory()->employee()->create(['is_active' => false]);
        $this->post('/login', ['login' => $inactive->username, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $fresh = User::factory()->employee()->create(['must_change_password' => true]);
        $this->actingAs($fresh)->get('/my/computers')->assertRedirect(route('my.password.edit'));
    }
}
