<?php

namespace Tests\Feature;

use App\Models\AssetRequest;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\ComputerEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Засварын урсгалын эрсдэлтэй тохиолдлууд: давхар бүртгэх, актласан, засах формоор гаргах, устгах, салбар, хил */
class RepairFlowTest extends TestCase
{
    use RefreshDatabase;

    private function pc(array $o = []): Computer
    {
        return Computer::factory()->create($o + ['category' => 'processor', 'status' => 'active', 'assigned_to' => null]);
    }

    public function test_cannot_register_twice_or_decommissioned_or_trashed(): void
    {
        $admin = User::factory()->admin()->create();
        $c = $this->pc();
        $this->actingAs($admin)->post(route('computers.repair', $c), ['note' => 'Асахгүй байна'])->assertSessionHasNoErrors();
        $position = $c->fresh()->repair_position;

        // Давхар бүртгэх: дараалал, түүх давхардахгүй
        $this->actingAs($admin)->post(route('computers.repair', $c), ['note' => 'Дахин'])->assertSessionHasErrors();
        $this->assertSame($position, $c->fresh()->repair_position);
        $this->assertSame(1, ComputerEvent::where('computer_id', $c->id)->where('type', 'repair')->count());

        // Актласан төхөөрөмжийг засварт оруулахгүй
        $dead = $this->pc(['status' => 'decommissioned']);
        $this->actingAs($admin)->post(route('computers.repair', $dead), ['note' => 'Засах'])->assertSessionHasErrors();
        $this->assertSame('decommissioned', $dead->fresh()->status->value);
    }

    public function test_leaving_repair_by_edit_form_closes_requests_and_keeps_numbers_continuous(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();
        [$a, $b, $c] = [$this->pc(['assigned_user_id' => $emp->id]), $this->pc(), $this->pc()];
        $req = AssetRequest::create(['user_id' => $emp->id, 'type' => 'issue', 'computer_id' => $a->id, 'description' => 'x']);
        $this->actingAs($admin)->post(route('requests.repair', $req));
        foreach ([$b, $c] as $x) {
            $this->actingAs($admin)->post(route('computers.repair', $x), ['note' => 'Эвдэрсэн']);
        }

        // A-г засах формоор «Ашиглагдаж байгаа» болгоход: дараалнаас гарч, хүсэлт хаагдана
        $this->actingAs($admin)->put("/computers/{$a->id}", ['name' => $a->name, 'type' => 'desktop', 'status' => 'active', 'inventory_number' => $a->inventory_number])
            ->assertSessionHasNoErrors();
        $this->assertNull($a->fresh()->repair_position);
        $this->assertSame('done', $req->fresh()->status);

        // B-г устгахад: C дугаар #1 болно (завсаргүй), хуудас алдаагүй
        $this->actingAs($admin)->delete("/computers/{$b->id}");
        $this->assertSame([$c->id => 1], Computer::repairQueueNumbers());
        $this->actingAs($admin)->get('/repairs')->assertOk();
    }

    public function test_branches_have_independent_queues_and_moves(): void
    {
        $adminA = User::factory()->admin()->create();
        $farBranch = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $adminB = User::factory()->admin()->create(['branch_id' => $farBranch->id]);
        $super = User::factory()->superadmin()->create();
        [$a1, $a2] = [$this->pc(['branch_id' => $adminA->branch_id]), $this->pc(['branch_id' => $adminA->branch_id])];
        [$b1, $b2] = [$this->pc(['branch_id' => $farBranch->id]), $this->pc(['branch_id' => $farBranch->id])];

        // Холилдсон дарааллаар (Супер админ, Админууд) засварт оруулна
        $this->actingAs($super)->post(route('computers.repair', $b1), ['note' => 'Эвдэрсэн']);
        $this->actingAs($adminA)->post(route('computers.repair', $a1), ['note' => 'Эвдэрсэн']);
        $this->actingAs($adminB)->post(route('computers.repair', $b2), ['note' => 'Эвдэрсэн']);
        $this->actingAs($super)->post(route('computers.repair', $a2), ['note' => 'Эвдэрсэн']);

        // Админ А: өөрийн дараалал #1, #2; дээш/доош өөрийн дотор
        $this->actingAs($adminA);
        $this->assertSame([$a1->id => 1, $a2->id => 2], Computer::repairQueueNumbers());
        $this->actingAs($adminA)->post(route('repairs.move', $a2), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->actingAs($adminA);
        $this->assertSame([$a2->id => 1, $a1->id => 2], Computer::repairQueueNumbers());

        // Админ B-ийн дараалал өөрчлөгдөөгүй
        $this->actingAs($adminB);
        $this->assertSame([$b1->id => 1, $b2->id => 2], Computer::repairQueueNumbers());

        // Супер админ: бүх 4, давхардалгүй 1..4; өөр салбарын төхөөрөмжийг Админ хөдөлгөж чадахгүй
        $this->actingAs($super);
        $this->assertSame([1, 2, 3, 4], array_values(Computer::repairQueueNumbers()));
        $this->actingAs($adminA)->post(route('repairs.move', $b1), ['direction' => 'down'])->assertNotFound();
        $this->actingAs($adminA)->patch(route('repairs.update', $b1), ['stage' => 'done'])->assertNotFound();
    }

    public function test_invalid_inputs_and_boundaries(): void
    {
        $admin = User::factory()->admin()->create();
        $c = $this->pc();
        $this->actingAs($admin)->post(route('computers.repair', $c), ['note' => 'ab'])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('computers.repair', $c), ['note' => 'Эвдэрсэн', 'cost' => 'abc'])->assertSessionHasErrors('cost');
        $this->actingAs($admin)->post(route('computers.repair', $c), ['note' => 'Эвдэрсэн', 'cost' => '-5'])->assertSessionHasErrors('cost');
        $this->actingAs($admin)->post(route('computers.repair', $c), ['note' => 'Эвдэрсэн'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('repairs.update', $c), ['stage' => 'буруу'])->assertSessionHasErrors('stage');
        $this->actingAs($admin)->post(route('repairs.move', $c), ['direction' => 'up'])->assertSessionHas('warning'); // ганц, хил
        $this->actingAs($admin)->post(route('repairs.move', $c), ['direction' => 'буруу'])->assertSessionHasErrors('direction');

        // Засварт биш төхөөрөмжийн явц шинэчлэх
        $other = $this->pc();
        $this->actingAs($admin)->patch(route('repairs.update', $other), ['stage' => 'done'])->assertSessionHasErrors('stage');
        // Ажилтан хандах эрхгүй
        $this->actingAs(User::factory()->employee()->create())->get('/repairs')->assertRedirect('/');
    }

    public function test_finish_without_holder_goes_to_storage_and_kit_items_stay(): void
    {
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();
        $main = $this->pc(['assigned_user_id' => $emp->id]);
        $mon = Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'kit_parent_id' => $main->id, 'assigned_user_id' => $emp->id]);
        $alone = $this->pc(['assigned_user_id' => null]);

        $this->actingAs($admin)->post(route('computers.repair', $main), ['note' => 'Асахгүй байна']);
        $this->assertSame('active', $mon->fresh()->status->value);    // дэлгэц засварт орохгүй
        $this->actingAs($admin)->patch(route('repairs.update', $main), ['stage' => 'done'])->assertSessionHasNoErrors();
        $this->assertSame(['active', 'active'], [$main->fresh()->status->value, $mon->fresh()->status->value]);

        $this->actingAs($admin)->post(route('computers.repair', $alone), ['note' => 'Асахгүй байна']);
        $this->actingAs($admin)->patch(route('repairs.update', $alone), ['stage' => 'done']);
        $this->assertSame('in_storage', $alone->fresh()->status->value);
    }
}
