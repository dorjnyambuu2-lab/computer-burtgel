<?php

namespace Tests\Feature;

use App\Models\AssetRequest;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\ComputerTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Системийн аудитаар олдсон логикийн алдаанууд дахин гарахгүйг шалгана */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function free(array $o = []): Computer
    {
        return Computer::factory()->create($o + ['category' => 'monitor', 'assigned_user_id' => null, 'assigned_to' => null, 'status' => 'in_storage']);
    }

    public function test_cannot_assign_repair_or_decommissioned_or_other_branch(): void
    {
        $super = User::factory()->superadmin()->create(['branch_id' => null]);
        $emp = User::factory()->employee()->create();
        $far = User::factory()->employee()->create(['branch_id' => Branch::create(['name' => 'Дархан', 'code' => 'DRH'])->id]);

        $rep = $this->free(['status' => 'in_repair']);
        $this->actingAs($super)->post("/computers/{$rep->id}/assign", ['user_id' => $emp->id])->assertSessionHasErrors('user_id');
        $this->assertSame(['in_repair', null], [$rep->fresh()->status->value, $rep->fresh()->assigned_user_id]);

        $dead = $this->free(['status' => 'decommissioned']);
        $this->actingAs($super)->post("/computers/{$dead->id}/assign", ['user_id' => $emp->id])->assertSessionHasErrors('user_id');
        $this->assertNull($dead->fresh()->assigned_user_id);

        // Өөр салбарын ажилтанд: нэг бүрээр, бөөнөөр, засах формоор, олон төхөөрөмж бүртгэхэд
        $pc = $this->free();
        $this->actingAs($super)->post("/computers/{$pc->id}/assign", ['user_id' => $far->id])->assertSessionHasErrors('user_id');
        $this->actingAs($super)->post(route('computers.bulk-assign.store'), ['user_id' => $far->id, 'computer_ids' => [$pc->id]])->assertSessionHasErrors('computer_ids');
        $this->actingAs($super)->put("/computers/{$pc->id}", ['name' => $pc->name, 'type' => 'other', 'status' => 'in_storage', 'inventory_number' => $pc->inventory_number,
            'branch_id' => $pc->branch_id, 'assigned_user_id' => $far->id])->assertSessionHasErrors('assigned_user_id');
        $this->actingAs($super)->post('/computers/multi', ['type' => 'desktop', 'status' => 'new', 'branch_id' => $pc->branch_id, 'assigned_user_id' => $far->id,
            'items' => [['category' => 'monitor'], ['category' => 'printer']]])->assertSessionHasErrors('assigned_user_id');
        $this->assertNull($pc->fresh()->assigned_user_id);

        // Бөөнөөр олгох хуудас: сонгосон ажилтны салбарын төхөөрөмж л
        $this->actingAs($super)->get(route('computers.bulk-assign', ['user_id' => $far->id]))->assertOk()->assertDontSee('name="computer_ids[]" value="'.$pc->id.'"', false);
    }

    public function test_transfer_issue_checks_branch_and_status(): void
    {
        $super = User::factory()->superadmin()->create(['branch_id' => null]);
        $emp = User::factory()->employee()->create();
        $far = User::factory()->employee()->create(['branch_id' => Branch::create(['name' => 'Дархан', 'code' => 'DRH'])->id]);
        $pc = $this->free();
        $tr = ComputerTransfer::create(['computer_id' => $pc->id, 'from_user_id' => $emp->id, 'status' => 'received', 'destination' => 'user']);

        $this->actingAs($super)->post("/transfers/{$tr->id}/issue", ['user_id' => $far->id])->assertSessionHasErrors('user_id');
        $this->assertSame('received', $tr->fresh()->status);
        // Хуудас SQLite-д ч нээгдэнэ (FIELD() биш)
        $this->actingAs($super)->get('/transfers')->assertOk();
    }

    public function test_users_with_devices_cannot_be_deleted_or_moved(): void
    {
        $super = User::factory()->superadmin()->create(['branch_id' => null]);
        $emp = User::factory()->employee()->create();
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        Computer::factory()->create(['category' => 'monitor', 'assigned_user_id' => $emp->id, 'status' => 'active']);

        $this->actingAs($super)->delete("/users/{$emp->id}")->assertSessionHas('warning');
        $this->assertNotNull($emp->fresh());

        $this->actingAs($super)->put("/users/{$emp->id}", ['name' => $emp->name, 'username' => $emp->username, 'role' => 'employee', 'branch_id' => $far->id, 'is_active' => 1])
            ->assertSessionHasErrors('branch_id');
        $this->assertNotSame($far->id, $emp->fresh()->branch_id);
    }

    public function test_assigning_holder_in_edit_form_makes_device_active(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();
        $pc = $this->free();
        $this->actingAs($admin)->put("/computers/{$pc->id}", ['name' => $pc->name, 'type' => 'other', 'status' => 'in_storage',
            'inventory_number' => $pc->inventory_number, 'assigned_user_id' => $emp->id])->assertSessionHasNoErrors();
        $this->assertSame(['active', $emp->id], [$pc->fresh()->status->value, $pc->fresh()->assigned_user_id]);
    }

    public function test_archiving_closes_open_requests_and_transfers(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();
        $pc = Computer::factory()->create(['category' => 'processor', 'assigned_user_id' => $emp->id, 'status' => 'active']);
        $req = AssetRequest::create(['user_id' => $emp->id, 'type' => 'issue', 'computer_id' => $pc->id, 'description' => 'Асахгүй']);
        $tr = ComputerTransfer::create(['computer_id' => $pc->id, 'from_user_id' => $emp->id, 'status' => 'pending', 'destination' => 'user']);

        $this->actingAs($admin)->delete("/computers/{$pc->id}");
        $this->assertSame(['rejected', 'Төхөөрөмж архивлагдсан.'], [$req->fresh()->status, $req->fresh()->admin_note]);
        $this->assertSame('rejected', $tr->fresh()->status);
    }

    public function test_take_back_during_repair_keeps_it_in_queue(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();
        $pc = Computer::factory()->create(['category' => 'processor', 'assigned_user_id' => $emp->id, 'status' => 'active']);
        $this->actingAs($admin)->post("/computers/{$pc->id}/repair", ['note' => 'Асахгүй байна']);

        $this->actingAs($admin)->post("/computers/{$pc->id}/return")->assertSessionHas('success', fn ($m) => str_contains($m, 'Засварын дараалалд үлдэнэ'));
        $this->assertSame(['in_repair', null], [$pc->fresh()->status->value, $pc->fresh()->assigned_user_id]);
        $this->assertNotNull($pc->fresh()->repair_position);

        // Засвар дуусахад хариуцагчгүй тул агуулахад
        $this->actingAs($admin)->patch(route('repairs.update', $pc), ['stage' => 'done'])->assertSessionHasNoErrors();
        $this->assertSame('in_storage', $pc->fresh()->status->value);
    }
}
