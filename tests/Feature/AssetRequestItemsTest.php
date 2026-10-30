<?php

namespace Tests\Feature;

use App\Models\AssetRequest;
use App\Models\AssetRequestItem;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\AssetRequestItemsDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Нэг захиалгад олон төхөөрөмж; Админ мөр бүрийг тусад нь шийдвэрлэнэ */
class AssetRequestItemsTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $emp, array $items = [['name' => 'Процессор'], ['name' => 'Printer'], ['name' => 'UPS', 'quantity' => 2]]): AssetRequest
    {
        $this->actingAs($emp)->post('/my/requests', ['type' => 'request', 'items' => $items, 'description' => 'Шинэ ажлын байр'])
            ->assertSessionHasNoErrors();

        return AssetRequest::latest('id')->firstOrFail();
    }

    public function test_employee_orders_several_devices_in_one_request(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $r = $this->order($emp);

        $this->assertSame(1, AssetRequest::count());
        $this->assertSame('Процессор, Printer, UPS ×2', $r->subject);
        $this->assertSame([['Процессор', 1], ['Printer', 1], ['UPS', 2]], $r->items->map(fn ($i) => [$i->name, $i->quantity])->all());
        $this->assertSame(['pending', 'pending', 'pending'], $r->items->pluck('status')->all());

        // Хоосон, хэт олон, буруу тоо
        $this->actingAs($emp)->post('/my/requests', ['type' => 'request', 'description' => 'x'])->assertSessionHasErrors('subject');
        $this->actingAs($emp)->post('/my/requests', ['type' => 'request', 'items' => [['name' => '']], 'description' => 'x'])->assertSessionHasErrors('items.0.name');
        $this->actingAs($emp)->post('/my/requests', ['type' => 'request', 'items' => [['name' => 'UPS', 'quantity' => 0]], 'description' => 'x'])->assertSessionHasErrors('items.0.quantity');
        $this->assertSame(1, AssetRequest::count());

        // Хуучин хэлбэр («Дэлгэц ×2» гарчиг) нэг мөр болно
        $this->actingAs($emp)->post('/my/requests', ['type' => 'request', 'subject' => 'Дэлгэц ×2', 'description' => 'x'])->assertSessionHasNoErrors();
        $this->assertSame([['Дэлгэц', 2]], AssetRequest::latest('id')->first()->items->map(fn ($i) => [$i->name, $i->quantity])->all());

        // Ажилтны нүүр хуудсанд мөр бүр харагдана
        $this->actingAs($emp)->get('/')->assertOk()->assertSeeInOrder(['Процессор', 'Printer', 'UPS ×2']);
    }

    public function test_admin_decides_each_item_separately(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $r = $this->order($emp);
        [$pc, $printer, $ups] = $r->items->all();

        // Процессор зөвшөөрч, Printer татгалзана (шалтгаантай), UPS хүлээгдэж буй хэвээр → хэсэгчлэн
        $this->actingAs($admin)->patch(route('requests.update', $r), ['items' => [
            $pc->id => ['status' => 'approved'],
            $printer->id => ['status' => 'rejected', 'note' => 'Нөөцөд байхгүй'],
            $ups->id => ['status' => 'pending'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['approved', 'rejected', 'pending'], $r->items()->pluck('status')->all());
        $this->assertSame('Нөөцөд байхгүй', $printer->fresh()->note);
        $this->assertSame('in_progress', $r->fresh()->status);
        $this->assertSame('Хэсэгчлэн шийдвэрлэсэн', $r->fresh()->load('items')->statusLabel());
        Notification::assertSentTo($emp, AssetRequestItemsDecided::class, fn ($n) => count($n->items) === 2);

        // Хуудас: мөр бүр, «бүгдийг зөвшөөрөх»
        $this->actingAs($admin)->get('/requests')->assertOk()->assertSee('Нөөцөд байхгүй')->assertSee('Хэсэгчлэн шийдвэрлэсэн')
            ->assertSee('Хүлээгдэж буйг бүгдийг зөвшөөрөх')->assertSee('name="items['.$ups->id.'][status]"', false);

        // Хүлээгдэж буйг бүгдийг зөвшөөрөх → UPS зөвшөөрсөн; өгөөгүй тул «Зөвшөөрсөн»
        $this->actingAs($admin)->patch(route('requests.update', $r), ['approve_all' => 1])->assertSessionHasNoErrors();
        $this->assertSame('approved', $ups->fresh()->status);
        $this->assertSame(['in_progress', 'Зөвшөөрсөн'], [$r->fresh()->status, $r->fresh()->load('items')->statusLabel()]);

        // Зөвшөөрснийг хүлээлгэн өгөхөд → дууссан
        $this->actingAs($admin)->patch(route('requests.update', $r), ['items' => [
            $pc->id => ['status' => 'delivered'], $ups->id => ['status' => 'delivered'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('done', $r->fresh()->status);

        // Буруу төлөв
        $this->actingAs($admin)->patch(route('requests.update', $r), ['items' => [$pc->id => ['status' => 'xx']]])->assertSessionHasErrors('items.'.$pc->id.'.status');
    }

    public function test_processor_can_be_given_without_its_kit_items(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $this->actingAs($admin)->post('/computers', ['type' => 'desktop', 'status' => 'in_storage', 'categories' => ['processor'], 'with_accessories' => ['mouse', 'keyboard']])
            ->assertSessionHasNoErrors();
        $pc = \App\Models\Computer::where('category', 'processor')->firstOrFail();
        [$mouse, $keyboard] = $pc->kitItems()->get()->all();

        // Процессор + mouse (keyboard-гүй): keyboard иж бүрдлээс салж агуулахад
        $this->actingAs($admin)->post(route('computers.bulk-assign.store'), ['user_id' => $emp->id, 'computer_ids' => [$pc->id, $mouse->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Иж бүрдлээс салгасан'));
        $this->assertSame([$emp->id, $emp->id], [$pc->fresh()->assigned_user_id, $mouse->fresh()->assigned_user_id]);
        $this->assertSame($pc->id, $mouse->fresh()->kit_parent_id);
        $this->assertNull($keyboard->fresh()->assigned_user_id);
        $this->assertNull($keyboard->fresh()->kit_parent_id);
        $this->assertSame('in_storage', $keyboard->fresh()->status->value);

        // Процессорыг дангаар (бүх хэрэгсэлгүй)
        $this->actingAs($admin)->post('/computers', ['type' => 'desktop', 'status' => 'in_storage', 'name' => 'PC-2', 'categories' => ['processor'], 'with_accessories' => ['mouse']]);
        $pc2 = \App\Models\Computer::where('name', 'PC-2')->firstOrFail();
        $this->actingAs($admin)->post(route('computers.bulk-assign.store'), ['user_id' => $emp->id, 'computer_ids' => [$pc2->id]])->assertSessionHasNoErrors();
        $this->assertSame(0, $pc2->kitItems()->count());
        $this->assertSame($emp->id, $pc2->fresh()->assigned_user_id);

        // Иж бүрэн бол хэвээр иж бүрдэл
        $this->actingAs($admin)->post('/computers', ['type' => 'desktop', 'status' => 'in_storage', 'name' => 'PC-3', 'categories' => ['processor'], 'with_accessories' => ['mouse']]);
        $pc3 = \App\Models\Computer::where('name', 'PC-3')->firstOrFail();
        $this->actingAs($admin)->post(route('computers.bulk-assign.store'), ['user_id' => $emp->id, 'computer_ids' => [$pc3->id, $pc3->kitItems()->value('id')]])
            ->assertSessionHas('success', fn ($m) => ! str_contains($m, 'салгасан'));
        $this->assertSame(1, $pc3->kitItems()->where('assigned_user_id', $emp->id)->count());
    }

    public function test_all_rejected_and_branch_scope(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create(['branch_id' => $admin->branch_id]);
        $r = $this->order($emp, [['name' => 'UPS']]);
        $this->actingAs($admin)->patch(route('requests.update', $r), ['items' => [$r->items->first()->id => ['status' => 'rejected']]]);
        $this->assertSame('rejected', $r->fresh()->status);

        // Өөр салбарын админ хандахгүй
        $far = User::factory()->admin()->create(['branch_id' => Branch::create(['name' => 'Дархан', 'code' => 'DRH'])->id]);
        $this->actingAs($far)->patch(route('requests.update', $r), ['approve_all' => 1])->assertForbidden();
        $this->assertSame(1, AssetRequestItem::count());
    }
}
