<?php

namespace Tests\Feature;

use App\Models\AssetRequest;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Бүх GET хуудсыг гурван эрхээр нээж 500 хайна, мөн эрсдэлтэй урсгалуудыг шалгана: иж бүрдлийг олгох, буцаах,
 * салбар солих, дангаар сэргээх, утга оруулах, экспорт. (Алдаа хайлтаар олдсон алдаануудын давтагдахаас сэргийлэх тест.)
 */
class BugHuntTest extends TestCase
{
    use RefreshDatabase;

    private function seedWorld(): array
    {
        $super = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create();
        $emp = User::factory()->employee()->create();
        $pc = Computer::factory()->create(['category' => 'processor', 'status' => 'active', 'assigned_user_id' => $emp->id]);
        $mon = Computer::factory()->create(['category' => 'monitor', 'status' => 'active', 'kit_parent_id' => $pc->id, 'assigned_user_id' => $emp->id]);
        $trashed = Computer::factory()->create(['category' => 'printer', 'status' => 'active']);
        $trashed->delete();
        $req = AssetRequest::create(['user_id' => $emp->id, 'type' => 'issue', 'computer_id' => $pc->id, 'description' => 'x']);

        return compact('super', 'admin', 'emp', 'pc', 'mon', 'trashed', 'req');
    }

    public function test_smoke_every_get_route_for_every_role(): void
    {
        $w = $this->seedWorld();
        $params = ['computer' => $w['pc']->id, 'id' => $w['pc']->id, 'user' => $w['emp']->id, 'branch' => Branch::first()->id,
            'assetRequest' => $w['req']->id, 'log' => 1, 'token' => 'x', 'transfer' => 1, 'passwordRequest' => 1];
        $errors = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || str_starts_with($route->uri(), '_') || str_contains($route->uri(), 'storage/')) {
                continue;
            }
            $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $params[$m[1]] ?? 1, $route->uri());
            foreach (['super', 'admin', 'emp'] as $role) {
                $status = $this->actingAs($w[$role])->get('/'.ltrim($uri, '/'))->getStatusCode();
                if ($status >= 500) {
                    $errors[] = "$role GET /$uri → $status";
                }
            }
        }

        $this->assertSame([], $errors);
    }

    // ── Иж бүрдэл ба олгох / буцаах ──

    public function test_assign_moves_whole_kit(): void
    {
        $w = $this->seedWorld();
        $other = User::factory()->employee()->create();
        $w['pc']->update(['assigned_user_id' => null]);
        $w['mon']->update(['assigned_user_id' => null]);

        $this->actingAs($w['admin'])->post(route('computers.assign', $w['pc']), ['user_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $w['mon']->fresh()->assigned_user_id, 'Иж бүрдлийн дэлгэц компьютертэйгээ хамт олгогдох ёстой');
    }

    public function test_take_back_returns_whole_kit(): void
    {
        $w = $this->seedWorld();
        $this->actingAs($w['admin'])->post(route('computers.return', $w['pc']))->assertSessionHasNoErrors();
        $this->assertNull($w['mon']->fresh()->assigned_user_id, 'Иж бүрдлийн дэлгэц хамт буцаагдах ёстой');
    }

    public function test_kit_items_follow_main_status_and_holder_except_repair(): void
    {
        $w = $this->seedWorld();
        $mouse = Computer::factory()->create(['category' => 'mouse', 'status' => 'new', 'kit_parent_id' => $w['pc']->id, 'assigned_user_id' => null]);
        $data = ['type' => 'desktop', 'name' => $w['pc']->name, 'inventory_number' => $w['pc']->inventory_number, 'category' => 'processor'];

        // Засах формоор төлөв солиход хэрэгслүүд дагана
        $this->actingAs($w['admin'])->put("/computers/{$w['pc']->id}", $data + ['status' => 'in_storage'])->assertSessionHasNoErrors();
        $this->assertSame(['in_storage', 'in_storage'], [$w['mon']->fresh()->status->value, $mouse->fresh()->status->value]);

        // «Засварт» нь зөвхөн компьютерт
        $this->actingAs($w['admin'])->post(route('computers.repair', $w['pc']), ['note' => 'Асахгүй байна'])->assertSessionHasNoErrors();
        $this->assertSame('in_storage', $w['mon']->fresh()->status->value);

        // Хариуцагч солигдоход (Супер админ ч) хэрэгслүүд дагана
        $other = User::factory()->employee()->create();
        Computer::$transferring = true;
        $w['pc']->fresh()->update(['assigned_user_id' => $other->id, 'status' => 'active']);
        Computer::$transferring = false;
        $this->assertSame([$other->id, 'active'], [$mouse->fresh()->assigned_user_id, $mouse->fresh()->status->value]);
    }

    public function test_branch_change_moves_kit_items(): void
    {
        $w = $this->seedWorld();
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $w['pc']->update(['branch_id' => $far->id]);
        $this->assertSame($far->id, $w['mon']->fresh()->branch_id, 'Салбар солиход дагалдах хэрэгсэл хамт шилжих ёстой');
    }

    public function test_restoring_kit_item_alone_while_parent_trashed(): void
    {
        $w = $this->seedWorld();
        $this->actingAs($w['super'])->delete("/computers/{$w['pc']->id}");
        $this->assertSoftDeleted($w['mon']);

        // Зөвхөн дэлгэцийг сэргээвэл үндсэн компьютер нь хамт сэргэж, иж бүрдэл бүхэлдээ жагсаалтад (харагдахгүй тоологдох хэрэгсэлгүй)
        $this->actingAs($w['super'])->post(route('computers.restore', $w['mon']->id));
        $this->assertNull($w['mon']->fresh()->deleted_at);
        $this->assertNull($w['pc']->fresh()->deleted_at, 'Дагалдах хэрэгслийг сэргээхэд үндсэн компьютер хамт сэргэх ёстой');
        $page = $this->actingAs($w['super'])->get('/computers');
        $this->assertTrue($page->viewData('computers')->pluck('id')->contains($w['pc']->id));
        $this->assertSame(2, $page->viewData('deviceCount'));
    }

    // ── Утга оруулах ──

    public function test_ram_and_price_inputs(): void
    {
        $w = $this->seedWorld();
        $base = ['type' => 'desktop', 'status' => 'new', 'categories' => ['processor'], 'with_accessories' => [], 'purchase_date' => '2026-01-10'];

        $this->actingAs($w['admin'])->post('/computers', $base + ['name' => 'R1', 'ram_gb' => '8+8', 'purchase_price' => '1,250,000.50'])->assertSessionHasNoErrors();
        $c = Computer::where('name', 'R1')->firstOrFail();
        $this->assertSame([16, '8GB + 8GB', '1250000.50'], [$c->ram_gb, $c->ram_modules, (string) $c->purchase_price]);

        foreach (['8GB+', 'abc', '0', '-4'] as $bad) {
            $this->actingAs($w['admin'])->post('/computers', $base + ['name' => 'Bad', 'ram_gb' => $bad])->assertSessionHasErrors('ram_gb');
        }
        $this->actingAs($w['admin'])->post('/computers', $base + ['name' => 'Bad', 'purchase_price' => '12abc'])->assertSessionHasErrors('purchase_price');
    }

    public function test_export_csv_with_kit_items_and_search(): void
    {
        $w = $this->seedWorld();
        $csv = $this->actingAs($w['super'])->get('/computers/export')->assertOk()->streamedContent();
        $this->assertStringContainsString($w['mon']->inventory_number, $csv);
        $this->actingAs($w['super'])->get('/computers?q='.urlencode($w['mon']->inventory_number))->assertOk();
    }

    public function test_admin_without_branch_sees_nothing_but_pages_work(): void
    {
        $this->seedWorld();
        $lost = User::factory()->admin()->create(['branch_id' => null]);
        foreach (['/', '/computers', '/computers/create', '/transfers', '/repairs', '/activity'] as $url) {
            $this->actingAs($lost)->get($url)->assertOk();
        }
        $this->assertSame(0, $this->actingAs($lost)->get('/computers')->viewData('deviceCount'));
    }
}
