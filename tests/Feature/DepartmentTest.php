<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Computer;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Алба хэлтсийн хүснэгт: текст ↔ department_id синк, нэр солих, нэгтгэх, устгах, эрх, форм */
class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_links_department_and_normalises_case(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Computer::factory()->create(['department' => 'МТ алба', 'branch_id' => $admin->branch_id]);
        $b = Computer::factory()->create(['department' => '  мт   алба ', 'branch_id' => $admin->branch_id]);
        $emp = User::factory()->employee()->create(['department' => 'МТ АЛБА']);

        $this->assertSame(1, Department::count());
        $dept = Department::firstOrFail();
        $this->assertSame([$dept->id, $dept->id, $dept->id], [$a->department_id, $b->fresh()->department_id, $emp->department_id]);
        $this->assertSame('МТ алба', $b->fresh()->department); // жагсаалтын хэлбэрээр

        // Өөр салбарт ижил нэртэй тусдаа хэлтэс
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        Computer::factory()->create(['department' => 'МТ алба', 'branch_id' => $far->id]);
        $this->assertSame(2, Department::count());
    }

    public function test_rename_merge_and_delete_guard(): void
    {
        $super = User::factory()->superadmin()->create();
        $pc = Computer::factory()->create(['department' => 'сыы']);
        $emp = User::factory()->employee()->create(['department' => 'сыы']);
        $good = Department::create(['branch_id' => $pc->branch_id, 'name' => 'Санхүүгийн алба']);
        $typo = $pc->departmentRecord;

        // Нэгтгэх: «сыы» → «Санхүүгийн алба», «сыы» устна
        $this->actingAs($super)->post(route('departments.merge', $typo), ['target_id' => $good->id])->assertSessionHasNoErrors();
        $this->assertNull($typo->fresh());
        $this->assertSame(['Санхүүгийн алба', $good->id], [$pc->fresh()->department, $pc->fresh()->department_id]);
        $this->assertSame('Санхүүгийн алба', $emp->fresh()->department);

        // Нэр солиход төхөөрөмж, ажилтан дээр шууд
        $this->actingAs($super)->put(route('departments.update', $good), ['name' => 'Санхүү, эдийн засгийн алба'])->assertSessionHasNoErrors();
        $this->assertSame('Санхүү, эдийн засгийн алба', $pc->fresh()->department);
        $this->assertSame('Санхүү, эдийн засгийн алба', $emp->fresh()->department);

        // Ашиглагдаж буйг устгахгүй; давхардсан нэр хориотой; хоосныг устгана
        $this->actingAs($super)->delete(route('departments.destroy', $good))->assertSessionHasErrors('department');
        $this->actingAs($super)->post(route('departments.store'), ['name' => 'санхүү, эдийн засгийн алба', 'branch_id' => $pc->branch_id])
            ->assertSessionHasErrors('name');
        $empty = Department::create(['branch_id' => $pc->branch_id, 'name' => 'Хоосон']);
        $this->actingAs($super)->delete(route('departments.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertNull($empty->fresh());
    }

    public function test_admin_scope_and_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $mine = Department::create(['branch_id' => $admin->branch_id, 'name' => 'Миний алба']);
        $theirs = Department::create(['branch_id' => $far->id, 'name' => 'Дарханы алба']);

        $this->actingAs($admin)->get('/departments')->assertOk()->assertSee('Миний алба')->assertDontSee('Дарханы алба');
        $this->actingAs($admin)->put(route('departments.update', $theirs), ['name' => 'Х'])->assertForbidden();

        // Админы нэмсэн алба өөрийн салбарт
        $this->actingAs($admin)->post(route('departments.store'), ['name' => 'Шинэ алба', 'branch_id' => $far->id])->assertSessionHasNoErrors();
        $this->assertSame($admin->branch_id, Department::where('name', 'Шинэ алба')->value('branch_id'));

        // Бүртгэх, хэрэглэгчийн форм: жагсаалтаас сонгох
        $this->actingAs($admin)->get('/computers/create')->assertOk()->assertSee('data-department-select', false)->assertSee('Миний алба');
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('data-department-select', false);
        // Ажилтан хэрэглэх эрхгүй
        $this->actingAs(User::factory()->employee()->create())->get('/departments')->assertRedirect('/');
        $this->assertNotNull($mine);
    }
}
