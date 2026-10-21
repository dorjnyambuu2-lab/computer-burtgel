<?php

namespace Tests\Feature;

use App\Enums\ComputerStatus;
use App\Enums\RepairStage;
use App\Models\AssetRequest;
use App\Models\Computer;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use App\Notifications\RepairQueueDigest;
use App\Notifications\RepairStatusChanged;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RepairQueueAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function computer(array $attrs = []): Computer
    {
        return Computer::factory()->create(['status' => ComputerStatus::Active] + $attrs);
    }

    // ───────────── 1. Засварын дараалал, дэс дугаар, мэдэгдэл ─────────────

    public function test_repairs_are_queued_in_order_and_numbered_with_hash(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        [$a, $b, $c] = [$this->computer(['name' => 'PC-A']), $this->computer(['name' => 'PC-B']), $this->computer(['name' => 'PC-C'])];

        foreach ([$a, $b, $c] as $pc) {
            $this->actingAs($admin)->post(route('computers.repair', $pc), ['note' => 'Асахгүй байна'])->assertRedirect();
        }

        $this->assertSame([$a->id => 1, $b->id => 2, $c->id => 3], Computer::repairQueueNumbers());

        $this->actingAs($admin)->get(route('repairs.index'))
            ->assertOk()
            ->assertSeeInOrder(['#1', 'PC-A', '#2', 'PC-B', '#3', 'PC-C']);

        // Дунд байгааг дуусгахад дугаар тасралтгүй үлдэнэ
        $this->actingAs($admin)->patch(route('repairs.update', $b), ['stage' => 'done'])->assertRedirect();
        $this->assertSame([$a->id => 1, $c->id => 2], Computer::repairQueueNumbers());
        $this->assertSame(ComputerStatus::InStorage, $b->fresh()->status);

        // Байр солих
        $this->actingAs($admin)->post(route('repairs.move', $c), ['direction' => 'up'])->assertRedirect();
        $this->assertSame([$c->id => 1, $a->id => 2], Computer::repairQueueNumbers());
    }

    public function test_status_change_via_edit_form_also_enters_the_queue(): void
    {
        $pc = $this->computer();
        $pc->update(['status' => 'in_repair']);

        $this->assertSame(1, $pc->fresh()->repairQueueNumber());
        $this->assertSame(RepairStage::Queued, $pc->fresh()->repair_stage);

        $pc->update(['status' => 'active']);
        $this->assertNull($pc->fresh()->repair_position);
    }

    public function test_holder_and_other_admins_are_notified_by_system_and_email(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $pc = $this->computer(['assigned_user_id' => $employee->id]);

        $this->actingAs($admin)->post(route('computers.repair', $pc), ['note' => 'Дэлгэц анивчина']);
        $this->actingAs($admin)->patch(route('repairs.update', $pc), ['stage' => 'waiting_parts', 'note' => 'RAM захиалсан']);

        Notification::assertSentTo($employee, RepairStatusChanged::class, function ($n, $channels) {
            return in_array('mail', $channels, true) && in_array('database', $channels, true);
        });
        Notification::assertSentTo($employee, RepairStatusChanged::class, fn ($n) => $n->kind === 'stage');
        Notification::assertSentTo($otherAdmin, RepairStatusChanged::class);
        Notification::assertNotSentTo($admin, RepairStatusChanged::class); // үйлдэл хийсэн хүн өөртөө авахгүй
    }

    public function test_daily_digest_lists_queue_to_staff(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        User::factory()->employee()->create();
        $this->computer()->update(['status' => 'in_repair']);

        $this->artisan('repairs:digest')->assertSuccessful();

        Notification::assertSentTo($admin, RepairQueueDigest::class, fn ($n) => $n->computers->count() === 1);
        Notification::assertCount(1);
    }

    public function test_issue_request_can_be_sent_to_repair_queue(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $pc = $this->computer(['assigned_user_id' => $employee->id]);
        $req = AssetRequest::create(['user_id' => $employee->id, 'computer_id' => $pc->id, 'type' => 'issue', 'description' => 'Асахгүй']);

        $this->actingAs($admin)->post(route('requests.repair', $req))->assertRedirect();

        $this->assertSame(1, $pc->fresh()->repairQueueNumber());
        $this->assertSame('in_progress', $req->fresh()->status);

        $this->actingAs($admin)->patch(route('repairs.update', $pc), ['stage' => 'done']);
        $this->assertSame('done', $req->fresh()->status);
        $this->assertSame(ComputerStatus::Active, $pc->fresh()->status);
    }

    // ───────────── 2. Эрх ─────────────

    public function test_only_superadmin_registers_users(): void
    {
        $admin = User::factory()->admin()->create();
        $super = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get('/users')->assertForbidden();
        $this->actingAs($admin)->post('/users', [])->assertForbidden();

        $this->actingAs($super)->post('/users', [
            'name' => 'Шинэ админ', 'username' => 'newadmin', 'email' => 'new@example.com',
            'role' => 'admin', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_active' => 1,
        ])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['username' => 'newadmin', 'role' => 'admin']);
    }

    public function test_employee_cannot_change_repair_status(): void
    {
        $employee = User::factory()->employee()->create();
        $pc = $this->computer();
        $pc->update(['status' => 'in_repair']);

        $this->actingAs($employee)->patch(route('repairs.update', $pc), ['stage' => 'done'])->assertRedirect('/');
        $this->assertSame(ComputerStatus::InRepair, $pc->fresh()->status);
    }

    // ───────────── 3. Шүүлт, эрэмбэ ─────────────

    public function test_computers_can_be_sorted_and_filtered_by_admin_and_date(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->computer(['name' => 'AAA-PC', 'created_at' => '2026-01-05']);
        $this->computer(['name' => 'ZZZ-PC', 'created_at' => '2026-03-05']);
        $this->actingAs($other);
        $this->computer(['name' => 'MMM-PC', 'created_at' => '2026-02-05']);

        $this->actingAs($admin)->get('/computers?sort=name&dir=asc')->assertSeeInOrder(['AAA-PC', 'MMM-PC', 'ZZZ-PC']);
        $this->actingAs($admin)->get('/computers?sort=name&dir=desc')->assertSeeInOrder(['ZZZ-PC', 'MMM-PC', 'AAA-PC']);
        $this->actingAs($admin)->get('/computers?created_by='.$other->id)->assertSee('MMM-PC')->assertDontSee('AAA-PC');
        $this->actingAs($admin)->get('/computers?date_from=2026-02-01&date_to=2026-02-28')
            ->assertSee('MMM-PC')->assertDontSee('AAA-PC')->assertDontSee('ZZZ-PC');
    }

    // ───────────── 4. Устгах ─────────────

    public function test_delete_is_soft_and_force_delete_requires_superadmin_password(): void
    {
        $admin = User::factory()->admin()->create();
        $super = User::factory()->superadmin()->create(['password' => 'super-secret-1']);
        $pc = $this->computer();

        $this->actingAs($admin)->delete(route('computers.destroy', $pc));
        $this->assertSoftDeleted($pc);
        $this->assertSame($admin->id, Computer::withTrashed()->find($pc->id)->deleted_by);

        // Админ: Супер админы нэвтрэх нэр/нууц үг буруу, эсвэл өөрийнхөө нууц үгээр бол устгагдахгүй
        $this->actingAs($admin)->delete(route('computers.force-delete', $pc->id), ['password' => 'super-secret-1'])
            ->assertSessionHasErrorsIn('forceDelete', 'approver');
        $this->actingAs($admin)->delete(route('computers.force-delete', $pc->id), ['approver' => $super->username, 'password' => 'wrong'])
            ->assertSessionHasErrorsIn('forceDelete', 'password');
        $this->actingAs($admin)->delete(route('computers.force-delete', $pc->id), ['approver' => $admin->username, 'password' => 'password'])
            ->assertSessionHasErrorsIn('forceDelete', 'password');
        $this->assertSoftDeleted($pc);

        // Супер админ нууц үггүй / буруу нууц үгтэй бол устгагдахгүй
        $this->actingAs($super)->delete(route('computers.force-delete', $pc->id), [])->assertSessionHasErrorsIn('forceDelete', 'password');
        $this->actingAs($super)->delete(route('computers.force-delete', $pc->id), ['password' => 'wrong'])->assertSessionHasErrorsIn('forceDelete', 'password');
        $this->assertSoftDeleted($pc);

        $this->actingAs($super)->delete(route('computers.force-delete', $pc->id), ['password' => 'super-secret-1'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('computers', ['id' => $pc->id]);
    }

    public function test_admin_can_force_delete_with_superadmin_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $super = User::factory()->superadmin()->create(['password' => 'super-secret-1']);
        $pc = $this->computer();
        $pc->delete();

        $this->actingAs($admin)->delete(route('computers.force-delete', $pc->id), [
            'approver' => $super->username, 'password' => 'super-secret-1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('computers', ['id' => $pc->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'force_delete_approved', 'subject_id' => $pc->id, 'user_id' => $admin->id]);
    }

    // ───────────── Автомат бүртгэл, дэс дугаар ─────────────

    public function test_registration_auto_fills_inventory_name_and_sequence(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->computer();

        // Зөвхөн төрөл, төлөв: бүртгэлийн дугаар, нэр хоосон
        $this->actingAs($admin)->post('/computers', ['type' => 'laptop', 'status' => 'active', 'brand' => 'Dell', 'model' => 'Latitude 5440'])
            ->assertRedirect();
        $laptop = Computer::latest('id')->first();

        $this->assertSame($first->sequence_number + 1, $laptop->sequence_number);
        $this->assertSame('NB-0001', $laptop->inventory_number);
        $this->assertSame('Dell Latitude 5440', $laptop->name);
        $this->assertSame($admin->id, $laptop->created_by);

        // Ангилалтай бол угтвар нь ангиллаар
        $this->actingAs($admin)->post('/computers', ['type' => 'other', 'status' => 'active', 'categories' => ['monitor']]);
        $this->assertSame('MON-0001', Computer::latest('id')->first()->inventory_number);
    }

    public function test_sequence_numbers_are_never_reused_after_delete(): void
    {
        $a = $this->computer();
        $b = $this->computer();
        $b->forceDelete();
        $c = $this->computer();

        $this->assertSame($a->sequence_number + 2, $c->sequence_number);
    }

    public function test_save_and_new_prefills_next_form_and_search_by_hash(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/computers', [
            'type' => 'desktop', 'status' => 'active', 'brand' => 'HP', 'model' => 'ProDesk 400',
            'department' => 'Санхүү', 'location' => '3-р давхар', 'serial_number' => 'SN-UNIQUE-1', 'save_and_new' => 1,
        ]);
        $pc = Computer::latest('id')->first();
        $response->assertRedirect(route('computers.create', ['from' => $pc->id]));

        // Шинэ форм: нийтлэг мэдээлэл хуулагдсан, серийн дугаар хуулагдаагүй
        $this->actingAs($admin)->get(route('computers.create', ['from' => $pc->id]))
            ->assertOk()->assertSee('ProDesk 400')->assertSee('Санхүү')->assertDontSee('SN-UNIQUE-1')
            ->assertSee('#'.($pc->sequence_number + 1));

        $this->actingAs($admin)->get('/computers?q=%23'.$pc->sequence_number)->assertSee('ProDesk 400');
    }

    // ───────────── 5. Нууц үг ─────────────

    public function test_default_password_forces_change_on_login(): void
    {
        $super = User::factory()->superadmin()->create(['password' => config('security.superadmin_default_password')]);

        $this->post('/login', ['login' => $super->username, 'password' => config('security.superadmin_default_password')])
            ->assertRedirect(route('my.password.edit'));
        $this->assertTrue($super->fresh()->must_change_password);

        $this->get('/computers')->assertRedirect(route('my.password.edit'));

        $this->put(route('my.password.update'), [
            'current_password' => config('security.superadmin_default_password'),
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($super->fresh()->must_change_password);
        $this->get('/computers')->assertOk();
    }

    public function test_seeder_creates_superadmin_with_default_password_and_keeps_changed_one(): void
    {
        $this->seed(\Database\Seeders\UserSeeder::class);
        $super = User::where('username', 'superadmin')->firstOrFail();
        $this->assertTrue(Hash::check(config('security.superadmin_default_password'), $super->password));
        $this->assertTrue($super->must_change_password);

        $super->update(['password' => 'changed-pass-1']);
        $this->seed(\Database\Seeders\UserSeeder::class);
        $this->assertTrue(Hash::check('changed-pass-1', $super->fresh()->password));
    }

    public function test_admin_forgot_password_sends_request_to_superadmin(): void
    {
        Notification::fake();
        $super = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();

        $this->post(route('password.email'), ['login' => $admin->username])->assertSessionHas('status');
        $this->post(route('password.email'), ['login' => $admin->username]); // давхар хүсэлт үүсэхгүй

        $this->assertSame(1, PasswordResetRequest::where('user_id', $admin->id)->where('status', 'pending')->count());
        Notification::assertSentTo($super, PasswordResetRequested::class);

        // Супер админ түр нууц үг олгоно -> админ нэвтэрмэгц солих шаардлагатай
        $req = PasswordResetRequest::firstOrFail();
        $response = $this->actingAs($super)->post(route('password-requests.resolve', $req));
        $temp = $response->getSession()->get('temporary_password')['password'];

        $this->assertTrue(Hash::check($temp, $admin->fresh()->password));
        $this->assertTrue($admin->fresh()->must_change_password);
        $this->assertSame('resolved', $req->fresh()->status);
    }

    public function test_superadmin_resets_password_with_email_link_and_recovery_key(): void
    {
        Notification::fake();
        config(['security.superadmin_recovery_key' => 'secret-recovery-key']);
        $super = User::factory()->superadmin()->create();

        $this->post(route('password.email'), ['login' => $super->email]);

        $token = null;
        Notification::assertSentTo($super, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        // Буруу түлхүүр -> татгалзана
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $super->email, 'recovery_key' => 'wrong',
            'password' => 'new-super-pass', 'password_confirmation' => 'new-super-pass',
        ])->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('new-super-pass', $super->fresh()->password));

        $this->post(route('password.store'), [
            'token' => $token, 'email' => $super->email, 'recovery_key' => 'secret-recovery-key',
            'password' => 'new-super-pass', 'password_confirmation' => 'new-super-pass',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-super-pass', $super->fresh()->password));
    }

    public function test_new_pages_render(): void
    {
        $super = User::factory()->superadmin()->create();
        $employee = User::factory()->employee()->create();
        $pc = $this->computer(['assigned_user_id' => $employee->id, 'assigned_to' => $employee->name]);
        $pc->update(['status' => 'in_repair']);
        $trashed = $this->computer(['name' => 'TRASHED-PC']);
        $trashed->delete();
        PasswordResetRequest::create(['user_id' => $employee->id, 'status' => 'pending']);

        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'x', 'email' => 'a@b.c']))->assertOk();
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));

        $this->actingAs($super)->get(route('computers.show', $pc))->assertOk()->assertSee('#1')->assertSee('Явц шинэчлэх');
        $this->actingAs($super)->get(route('computers.trash'))->assertOk()->assertSee('TRASHED-PC')->assertSee('Супер админы нууц үг');
        $this->actingAs($super)->get(route('password-requests.index'))->assertOk()->assertSee($employee->name);
        $this->actingAs($super)->get('/users?sort=created_at&dir=desc&date_from=2020-01-01')->assertOk();
        $this->actingAs($super)->get(route('requests.index'))->assertOk();

        $this->actingAs($employee)->get('/')->assertOk()->assertSee('#1');
        $this->actingAs($employee)->get(route('my.computers.show', $pc->id))->assertOk()->assertSee('#1');
    }

    public function test_notifications_page_lists_and_marks_read(): void
    {
        $employee = User::factory()->employee()->create();
        $admin = User::factory()->admin()->create();
        $pc = $this->computer(['assigned_user_id' => $employee->id]);

        $this->actingAs($admin)->post(route('computers.repair', $pc), ['note' => 'Гар ажиллахгүй']);

        $this->actingAs($employee)->get(route('notifications.index'))->assertOk()->assertSee('Засварын дараалалд орлоо');
        $id = $employee->notifications()->first()->id;
        $this->actingAs($employee)->get(route('notifications.open', $id))->assertRedirect(route('my.computers.show', $pc->id));
        $this->assertSame(0, $employee->fresh()->unreadNotifications()->count());
    }
}
