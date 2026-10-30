<?php

namespace Tests\Feature;

use App\Models\Computer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Устгасан хуудас: иж бүрдлийн хэрэгслүүд үндсэн компьютерийнхээ доор, дугаарт тоологдохгүй */
class TrashPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_kit_items_are_nested_under_main_computer(): void
    {
        $super = User::factory()->superadmin()->create(['branch_id' => null]);
        $this->actingAs($super)->post('/computers', ['type' => 'desktop', 'status' => 'in_storage', 'name' => 'PC-KIT', 'categories' => ['processor'], 'with_accessories' => ['mouse', 'keyboard'],
            'branch_id' => User::factory()->admin()->create()->branch_id])->assertSessionHasNoErrors();
        $pc = Computer::where('name', 'PC-KIT')->firstOrFail();
        $this->actingAs($super)->delete("/computers/{$pc->id}");
        $this->assertSame(3, Computer::onlyTrashed()->count());

        $this->actingAs($super)->get('/computers/trash')->assertOk()
            ->assertSee('Архивт 1 бүртгэл байна')->assertSee('+2 иж бүрдэл')->assertSee('>#1<', false)->assertDontSee('>#2<', false)
            ->assertSeeInOrder(['PC-KIT', 'Mouse', 'Keyboard']);

        // Үндсэн компьютерийг сэргээхэд хэрэгслүүд хамт
        $this->actingAs($super)->post("/computers/{$pc->id}/restore");
        $this->assertSame(0, Computer::onlyTrashed()->count());
    }
}
