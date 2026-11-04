<?php

namespace Tests\Feature;

use App\Models\AssetRequest;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\User;
use App\Notifications\AssetRequestItemsDecided;
use App\Notifications\WarrantyExpiringDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Захиалгаас төхөөрөмж олгох, идэвхгүй болгох хамгаалалт, баталгааны сануулга */
class DeliveryAndWarrantyTest extends TestCase
{
    use RefreshDatabase;

    private function free(array $o = []): Computer
    {
        return Computer::factory()->create($o + ['category' => 'ups', 'assigned_user_id' => null, 'assigned_to' => null, 'status' => 'in_storage']);
    }

    private function order(User $emp, int $quantity = 1): AssetRequest
    {
        $this->actingAs($emp)->post('/my/requests', ['type' => 'request', 'items' => [['name' => 'UPS', 'quantity' => $quantity]], 'description' => 'Хэрэгтэй'])->assertSessionHasNoErrors();

        return AssetRequest::latest('id')->firstOrFail();
    }

    public function test_delivering_from_order_assigns_device_and_marks_item(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $order = $this->order($emp, 2);
        $item = $order->items->first();
        [$a, $b] = [$this->free(['branch_id' => $admin->branch_id]), $this->free(['branch_id' => $admin->branch_id])];

        // Хүсэлтийн хуудсанд холбоос, олгох хуудсанд захиалгын мөр
        $link = route('computers.bulk-assign', ['user_id' => $emp->id, 'request_item' => $item->id]);
        $this->actingAs($admin)->get('/requests')->assertOk()->assertSee('Төхөөрөмж олгох →')->assertSee($link);
        $this->actingAs($admin)->get($link)->assertOk()->assertSee('Захиалга:')->assertSee('name="request_item" value="'.$item->id.'"', false);

        // 2-оос 1-ийг олгоход: хүлээлгэн өгөөгүй, дутуу
        $this->actingAs($admin)->post(route('computers.bulk-assign.store'), ['user_id' => $emp->id, 'computer_ids' => [$a->id], 'request_item' => $item->id])
            ->assertRedirect(route('requests.index', ['type' => 'request']));
        $this->assertSame($emp->id, $a->fresh()->assigned_user_id);
        $this->assertSame(['approved', 1], [$item->fresh()->status, $item->fresh()->delivered_quantity]);
        $this->assertStringContainsString($a->inventory_number, $item->fresh()->note);
        $this->actingAs($admin)->get('/requests')->assertSee('1/2 олгосон');

        // Үлдсэнийг олгоход: «Хүлээлгэн өгсөн», захиалга дууссан, ажилтанд мэдэгдэл
        $this->actingAs($admin)->post(route('computers.bulk-assign.store'), ['user_id' => $emp->id, 'computer_ids' => [$b->id], 'request_item' => $item->id])->assertSessionHasNoErrors();
        $this->assertSame(['delivered', 2], [$item->fresh()->status, $item->fresh()->delivered_quantity]);
        $this->assertSame('done', $order->fresh()->status);
        Notification::assertSentTo($emp, AssetRequestItemsDecided::class);

        // Дууссан мөрөөр дахин олгохгүй
        $c = $this->free(['branch_id' => $admin->branch_id]);
        $this->actingAs($admin)->post(route('computers.bulk-assign.store'), ['user_id' => $emp->id, 'computer_ids' => [$c->id], 'request_item' => $item->id])
            ->assertSessionHasErrors('computer_ids');
        $this->assertNull($c->fresh()->assigned_user_id);
    }

    public function test_order_item_must_belong_to_employee_and_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $other = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $item = $this->order($emp)->items->first();
        $pc = $this->free(['branch_id' => $admin->branch_id]);

        // Өөр ажилтны мөрөөр: олгохгүй, хуудсанд мөр харагдахгүй
        $this->actingAs($admin)->post(route('computers.bulk-assign.store'), ['user_id' => $other->id, 'computer_ids' => [$pc->id], 'request_item' => $item->id])
            ->assertSessionHasErrors('computer_ids');
        $this->assertNull($pc->fresh()->assigned_user_id);
        $this->actingAs($admin)->get(route('computers.bulk-assign', ['user_id' => $other->id, 'request_item' => $item->id]))->assertOk()->assertDontSee('name="request_item"', false);

        // Өөр салбарын админ
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $farAdmin = User::factory()->admin()->create(['branch_id' => $far->id]);
        $farEmp = User::factory()->employee()->create(['branch_id' => $far->id]);
        $this->actingAs($farAdmin)->get(route('computers.bulk-assign', ['user_id' => $farEmp->id, 'request_item' => $item->id]))->assertOk()->assertDontSee('name="request_item"', false);
    }

    public function test_user_with_devices_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $pc = Computer::factory()->create(['category' => 'monitor', 'assigned_user_id' => $emp->id, 'status' => 'active', 'branch_id' => $admin->branch_id]);
        $form = ['name' => $emp->name, 'username' => $emp->username, 'role' => 'employee'];

        $this->actingAs($admin)->put("/users/{$emp->id}", $form)->assertSessionHasErrors('is_active');
        $this->assertTrue($emp->fresh()->is_active);

        // Буцаан авсны дараа болно
        $this->actingAs($admin)->post("/computers/{$pc->id}/return");
        $this->actingAs($admin)->put("/users/{$emp->id}", $form)->assertSessionHasNoErrors();
        $this->assertFalse($emp->fresh()->is_active);
    }

    public function test_warranty_digest_notifies_once_per_device_and_branch(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $farAdmin = User::factory()->admin()->create(['branch_id' => $far->id]);
        $super = User::factory()->superadmin()->create(['branch_id' => null]);
        $soon = Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'branch_id' => $admin->branch_id, 'warranty_until' => today()->addDays(30)]);
        Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'branch_id' => $admin->branch_id, 'warranty_until' => today()->addDays(200)]);
        Computer::factory()->create(['category' => 'monitor', 'status' => 'decommissioned', 'branch_id' => $admin->branch_id, 'warranty_until' => today()->addDays(30)]);
        Computer::factory()->create(['category' => 'mouse', 'status' => 'active', 'branch_id' => $admin->branch_id, 'warranty_until' => today()->addDays(30)]);

        $this->artisan('warranty:digest')->assertSuccessful();
        foreach ([$admin, $super] as $user) {
            Notification::assertSentTo($user, WarrantyExpiringDigest::class, fn ($n) => $n->computers->pluck('id')->all() === [$soon->id]);
        }
        Notification::assertNotSentTo($farAdmin, WarrantyExpiringDigest::class);
        $this->assertNotNull($soon->fresh()->warranty_notified_at);

        // Дахин ажиллуулахад давтахгүй
        Notification::fake();
        $this->artisan('warranty:digest')->assertSuccessful();
        Notification::assertNothingSent();

        // Огноо солигдвол дахин мэдэгдэнэ
        $soon->fresh()->update(['warranty_until' => today()->addDays(40)]);
        $this->assertNull($soon->fresh()->warranty_notified_at);
        $this->artisan('warranty:digest')->assertSuccessful();
        Notification::assertSentTo($admin, WarrantyExpiringDigest::class);
    }
}
