<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Computer;
use App\Models\ComputerTransfer;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Өмнө тестээр хамрагдаагүй урсгалууд: нууц үг сэргээх, шилжүүлгийн хүсэлт (хүлээн авах, олгох, татгалзах),
 * засварын дараалал, хэрэглэгч, салбар, мэдэгдэл, QR.
 */
class FlowTest extends TestCase
{
    use RefreshDatabase;

    private function pc(array $o = []): Computer
    {
        return Computer::factory()->create(['category' => 'processor', 'status' => 'active', 'assigned_to' => null] + $o);
    }

    public function test_password_reset_request_flow(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['username' => 'bold']);

        // Ажилтан нууц үгээ мартсан → Админд хүсэлт
        $this->post('/forgot-password', ['login' => 'bold'])->assertSessionHasNoErrors();
        $req = PasswordResetRequest::where('user_id', $emp->id)->firstOrFail();
        $this->post('/forgot-password', ['login' => 'bold']); // давтан дарвал шинэ хүсэлт үүсэхгүй
        $this->assertSame(1, PasswordResetRequest::count());

        // Админ түр нууц үг олгоно
        $this->actingAs($admin)->post(route('password-requests.resolve', $req))->assertSessionHasNoErrors();
        $temporary = session('temporary_password')['password'];
        $this->assertSame('resolved', $req->fresh()->status);
        $this->post('/logout');

        // Түр нууц үгээр нэвтэрмэгц шинэ нууц үг солих хуудас руу
        $this->post('/login', ['login' => 'bold', 'password' => $temporary])->assertRedirect();
        $this->get('/my/computers')->assertRedirect(route('my.password.edit'));
        $this->put('/my/password', ['current_password' => $temporary, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($emp->fresh()->must_change_password);
        $this->get('/my/computers')->assertOk();
    }

    public function test_pending_transfer_receive_issue_and_reject_with_kit(): void
    {
        $admin = User::factory()->admin()->create();
        $from = User::factory()->employee()->create();
        $to = User::factory()->employee()->create();
        $pc = $this->pc(['assigned_user_id' => $from->id]);
        $mon = Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'kit_parent_id' => $pc->id, 'assigned_user_id' => $from->id]);

        $t = ComputerTransfer::create(['computer_id' => $pc->id, 'from_user_id' => $from->id, 'to_user_id' => $to->id, 'status' => 'pending', 'reason' => 'Ажлаас гарсан']);
        $this->actingAs($admin)->post(route('transfers.receive', $t))->assertSessionHasNoErrors();
        $this->assertSame('in_storage', $pc->fresh()->status->value);
        $this->assertNull($mon->fresh()->assigned_user_id, 'Хүлээн авахад иж бүрдлийн дэлгэц хамт агуулахад орох ёстой');

        $this->actingAs($admin)->post(route('transfers.issue', $t), ['user_id' => $to->id])->assertSessionHasNoErrors();
        $this->assertSame($to->id, $pc->fresh()->assigned_user_id);
        $this->assertSame($to->id, $mon->fresh()->assigned_user_id, 'Олгоход иж бүрдлийн дэлгэц хамт олгогдох ёстой');
        $this->assertSame('completed', $t->fresh()->status);

        $other = $this->pc(['assigned_user_id' => $from->id]);
        $t2 = ComputerTransfer::create(['computer_id' => $other->id, 'from_user_id' => $from->id, 'status' => 'pending']);
        $this->actingAs($admin)->post(route('transfers.reject', $t2))->assertSessionHasNoErrors();
        $this->assertSame('rejected', $t2->fresh()->status);
        $this->assertSame($from->id, $other->fresh()->assigned_user_id);
    }

    public function test_repair_queue_move_and_finish(): void
    {
        $admin = User::factory()->admin()->create();
        $a = $this->pc(['name' => 'A']);
        $b = $this->pc(['name' => 'B']);
        foreach ([$a, $b] as $c) {
            $this->actingAs($admin)->post(route('computers.repair', $c), ['note' => 'Асахгүй байна'])->assertSessionHasNoErrors();
        }
        $this->assertSame([1, 2], [$a->fresh()->repairQueueNumber(), $b->fresh()->repairQueueNumber()]);

        $this->actingAs($admin)->post(route('repairs.move', $b), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame([2, 1], [$a->fresh()->repairQueueNumber(), $b->fresh()->repairQueueNumber()]);

        $this->actingAs($admin)->patch(route('repairs.update', $b), ['stage' => 'done', 'cost' => '75,000'])->assertSessionHasNoErrors();
        $this->assertNotSame('in_repair', $b->fresh()->status->value);
        $this->assertSame(1, $a->fresh()->repairQueueNumber());
        $this->assertEquals(75000, $b->events()->sum('cost'));
    }

    public function test_user_edit_delete_and_self_protection(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['name' => 'Хуучин']);

        $this->actingAs($admin)->put("/users/{$emp->id}", ['name' => 'Шинэ нэр', 'username' => $emp->username, 'email' => null, 'role' => 'employee', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('Шинэ нэр', $emp->fresh()->name);

        $this->actingAs($admin)->get("/users/{$admin->id}/edit")->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$emp->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $emp->id]);
    }

    public function test_branch_crud_and_delete_guard(): void
    {
        $super = User::factory()->superadmin()->create();
        $this->actingAs($super)->post('/branches', ['name' => 'Эрдэнэт', 'code' => 'ERD'])->assertSessionHasNoErrors();
        $b = Branch::where('code', 'ERD')->firstOrFail();
        $this->actingAs($super)->put("/branches/{$b->id}", ['name' => 'Эрдэнэт салбар', 'code' => 'ERD'])->assertSessionHasNoErrors();
        $this->assertSame('Эрдэнэт салбар', $b->fresh()->name);

        $this->pc(['branch_id' => $b->id]);
        $this->actingAs($super)->delete("/branches/{$b->id}")->assertSessionHasErrors('branch');
        $this->assertNotNull($b->fresh());

        $empty = Branch::create(['name' => 'Хоосон', 'code' => 'EMP']);
        $this->actingAs($super)->delete("/branches/{$empty->id}")->assertSessionHasNoErrors();
        $this->assertNull($empty->fresh());
    }

    public function test_notifications_open_and_read_all(): void
    {
        $admin = User::factory()->admin()->create();
        $colleague = User::factory()->admin()->create();
        $this->actingAs($colleague)->post('/computers', ['type' => 'desktop', 'status' => 'new', 'categories' => ['processor'], 'with_accessories' => [],
            'holder_surname' => 'Бат', 'holder_name' => 'Туяа', 'purchase_date' => '2026-01-10'])->assertSessionHasNoErrors();

        $n = $admin->notifications()->firstOrFail();
        $this->actingAs($admin)->get(route('notifications.open', $n->id))->assertRedirect($n->data['url']);
        $this->assertNotNull($n->fresh()->read_at);

        $this->actingAs($admin)->post(route('notifications.read-all'))->assertSessionHasNoErrors();
        $this->assertSame(0, $admin->unreadNotifications()->count());
        // Бусдын мэдэгдлийг нээж болохгүй
        $this->actingAs($colleague)->get(route('notifications.open', $n->id))->assertNotFound();
    }

    public function test_qr_resolve_png_and_label(): void
    {
        $admin = User::factory()->admin()->create();
        $c = $this->pc();

        $this->actingAs($admin)->get(route('computers.resolve', ['code' => $c->qr_code]))->assertRedirect(route('computers.show', $c));
        $this->actingAs($admin)->get(route('computers.resolve', ['code' => $c->inventory_number]))->assertRedirect(route('computers.show', $c));
        $this->actingAs($admin)->get(route('computers.resolve', ['code' => 'байхгүй-код']))->assertRedirect(route('computers.scan'));
        $this->actingAs($admin)->get(route('computers.qr-png', $c))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($admin)->get(route('computers.label', $c))->assertOk();
    }

    public function test_print_page_and_price_in_basic_info(): void
    {
        $admin = User::factory()->admin()->create();
        $c = $this->pc(['name' => 'Хэвлэх-PC', 'purchase_price' => 2500000]);
        Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'kit_parent_id' => $c->id, 'name' => 'Хамт-Дэлгэц']);

        // Төхөөрөмжийн хуудас: «Үндсэн мэдээлэл»-д үнэ, «Хэвлэх» товч
        $this->actingAs($admin)->get("/computers/{$c->id}")->assertOk()
            ->assertSee('2,500,000₮')->assertSee(route('computers.print', $c));

        // Хэвлэх хуудас: мэдээлэл, үнэ, иж бүрдэл, QR, гарын үсэг
        $this->actingAs($admin)->get(route('computers.print', $c))->assertOk()
            ->assertSee('Хэвлэх-PC')->assertSee($c->inventory_number)->assertSee('2,500,000₮')
            ->assertSee('Хамт-Дэлгэц')->assertSee('<svg', false)->assertSee('Хүлээн авсан');

        // Өөр салбарын Админ, ажилтан хэвлэх эрхгүй
        $far = User::factory()->admin()->create(['branch_id' => Branch::create(['name' => 'Дархан', 'code' => 'DRH'])->id]);
        $this->actingAs($far)->get(route('computers.print', $c))->assertNotFound();
        $this->actingAs(User::factory()->employee()->create())->get(route('computers.print', $c))->assertRedirect('/');
    }

    public function test_cost_summary_kit_total_and_unknown_price(): void
    {
        $admin = User::factory()->admin()->create();
        $pc = $this->pc(['purchase_price' => 2500000]);
        $mon = Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'kit_parent_id' => $pc->id, 'purchase_price' => 1250000]);
        Computer::factory()->create(['category' => 'mouse', 'status' => 'active', 'kit_parent_id' => $pc->id, 'purchase_price' => null]);
        $this->actingAs($admin)->post(route('computers.repair', $mon), ['note' => 'Асахгүй байна', 'cost' => '100,000']);

        // Компьютер дангаараа: 2,500,000; иж бүрдэл: 2,500,000 + 1,250,000 + засвар 100,000; mouse-ийн үнэ алга
        $cost = $pc->fresh()->costSummary();
        $this->assertEquals(2500000, $cost['total']);
        $this->assertEquals(['count' => 3, 'price' => 3750000, 'repair_cost' => 100000, 'total' => 3850000, 'missing' => 1], $cost['kit']);
        // Дэлгэцийн хуудсанд ч иж бүрдлийн нийт ижил
        $this->assertEquals(3850000, $mon->fresh()->costSummary()['kit']['total']);

        $this->actingAs($admin)->get("/computers/{$pc->id}")->assertOk()
            ->assertSee('иж бүрдэл 3 төхөөрөмж')->assertSee('3,850,000₮')->assertDontSee('төхөөрөмжийн үнэ оруулаагүй');

        // Үнэ ч, засвар ч мэдэгдэхгүй: «0₮» биш «—»
        $alone = $this->pc(['purchase_price' => null]);
        $this->assertNull($alone->costSummary()['total']);
        $this->assertNull($alone->costSummary()['kit']);
    }

    public function test_requests_page_tabs_counts_reply_and_handler(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Шийдэгч']);
        $emp = User::factory()->employee()->create(['name' => 'Цэцэг']);
        $pc = $this->pc(['assigned_user_id' => $emp->id]);
        \App\Models\AssetRequest::create(['user_id' => $emp->id, 'type' => 'issue', 'computer_id' => $pc->id, 'description' => 'Анивчаад байна']);
        $done = \App\Models\AssetRequest::create(['user_id' => $emp->id, 'type' => 'request', 'subject' => 'Дэлгэц', 'description' => 'Хэрэгтэй']);
        $this->actingAs($admin)->patch('/requests/'.$done->id, ['status' => 'done', 'admin_note' => 'Олгосон'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get('/requests')->assertOk()
            ->assertSee('Ажилтны хүсэлт')->assertSee('1</b> хүсэлт шийдвэрлэхийг хүлээж байна', false)
            ->assertSee('Шийдвэрлэх')->assertSee('Хариу:')->assertSee('Олгосон')->assertSee('Шийдэгч')
            ->assertSee(route('computers.show', $pc))
            ->assertViewHas('statusCounts', ['done' => 1, 'pending' => 1]);

        // Таб: зөвхөн хүлээгдэж буй
        $this->actingAs($admin)->get('/requests?status=pending')->assertOk()
            ->assertViewHas('requests', fn ($p) => $p->total() === 1 && $p->first()->status === 'pending');
    }

    public function test_profile_update_and_login_by_username_or_email(): void
    {
        $emp = User::factory()->employee()->create(['username' => 'saraa', 'email' => 'saraa@example.mn']);
        $this->post('/login', ['login' => 'saraa', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->post('/logout');
        $this->post('/login', ['login' => 'saraa@example.mn', 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->actingAs($emp)->put('/profile', ['surname' => 'Бат', 'name' => 'Сараа', 'email' => 'new@example.mn'])->assertSessionHasNoErrors();
        $this->assertSame(['Бат', 'new@example.mn'], [$emp->fresh()->surname, $emp->fresh()->email]);
    }
}
