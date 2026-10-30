<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Нэгжийн хүснэгт (алба хэлтэстэй адил): текст ↔ unit_id синк, нэр солих, нэгтгэх, устгах, эрх, форм */
class UnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_links_unit_and_normalises_case(): void
    {
        $admin = User::factory()->admin()->create();
        $a = Computer::factory()->create(['unit' => 'МЧ', 'branch_id' => $admin->branch_id]);
        $b = Computer::factory()->create(['unit' => '  мч ', 'branch_id' => $admin->branch_id]);
        $emp = User::factory()->employee()->create(['unit' => 'мч', 'branch_id' => $admin->branch_id]);

        $this->assertSame(1, Unit::count());
        $unit = Unit::firstOrFail();
        $this->assertSame([$unit->id, $unit->id, $unit->id], [$a->unit_id, $b->fresh()->unit_id, $emp->unit_id]);
        $this->assertSame('МЧ', $b->fresh()->unit);

        // Өөр салбарт ижил нэртэй тусдаа нэгж; хоосон бол холбоосгүй
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        Computer::factory()->create(['unit' => 'МЧ', 'branch_id' => $far->id]);
        $this->assertSame(2, Unit::count());
        $a->update(['unit' => null]);
        $this->assertNull($a->fresh()->unit_id);
    }

    public function test_rename_merge_and_delete_guard(): void
    {
        $super = User::factory()->superadmin()->create();
        $pc = Computer::factory()->create(['unit' => 'ы']);
        $emp = User::factory()->employee()->create(['unit' => 'ы', 'branch_id' => $pc->branch_id]);
        $good = Unit::create(['branch_id' => $pc->branch_id, 'name' => 'Захиргааны нэгж']);
        $typo = $pc->unitRecord;

        // Нэгтгэх: «ы» → «Захиргааны нэгж», «ы» устна, түүхэнд «Нэгтгэсэн»
        $this->actingAs($super)->post(route('units.merge', $typo), ['target_id' => $good->id])->assertSessionHasNoErrors();
        $this->assertNull($typo->fresh());
        $this->assertSame(['Захиргааны нэгж', $good->id], [$pc->fresh()->unit, $pc->fresh()->unit_id]);
        $this->assertSame('Захиргааны нэгж', $emp->fresh()->unit);
        $this->assertTrue(ActivityLog::where('subject_type', (new Unit)->getMorphClass())->where('action', 'merged')->exists());

        // Нэр солиход төхөөрөмж, ажилтан дээр шууд
        $this->actingAs($super)->put(route('units.update', $good), ['name' => 'Удирдлагын нэгж'])->assertSessionHasNoErrors();
        $this->assertSame(['Удирдлагын нэгж', 'Удирдлагын нэгж'], [$pc->fresh()->unit, $emp->fresh()->unit]);

        // Ашиглагдаж буйг устгахгүй; давхардсан нэр хориотой; хоосныг устгана
        $this->actingAs($super)->delete(route('units.destroy', $good))->assertSessionHasErrors('unit');
        $this->actingAs($super)->post(route('units.store'), ['name' => 'удирдлагын нэгж', 'branch_id' => $pc->branch_id])
            ->assertSessionHasErrors(['name' => 'Энэ салбарт «удирдлагын нэгж» нэгж бүртгэлтэй байна.']);
        $empty = Unit::create(['branch_id' => $pc->branch_id, 'name' => 'Хоосон']);
        $this->actingAs($super)->delete(route('units.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertNull($empty->fresh());
        $this->actingAs($super)->post(route('units.store'), ['name' => '', 'branch_id' => $pc->branch_id])
            ->assertSessionHasErrors(['name' => 'Нэгжийн нэрийг оруулна уу.']);
    }

    public function test_admin_scope_pages_and_forms(): void
    {
        $admin = User::factory()->admin()->create();
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        Unit::create(['branch_id' => $admin->branch_id, 'name' => 'Миний нэгж']);
        $theirs = Unit::create(['branch_id' => $far->id, 'name' => 'Дарханы нэгж']);

        $this->actingAs($admin)->get('/units')->assertOk()->assertSee('Миний нэгж')->assertDontSee('Дарханы нэгж')->assertSee('+ Нэгж нэмэх');
        $this->actingAs($admin)->get('/departments')->assertOk()->assertSee(route('units.index'), false);
        $this->actingAs($admin)->put(route('units.update', $theirs), ['name' => 'Х'])->assertForbidden();
        $this->actingAs($admin)->delete(route('units.destroy', $theirs))->assertForbidden();

        // Админы нэмсэн нэгж өөрийн салбарт
        $this->actingAs($admin)->post(route('units.store'), ['name' => 'Шинэ нэгж', 'branch_id' => $far->id])->assertSessionHasNoErrors();
        $this->assertSame($admin->branch_id, Unit::where('name', 'Шинэ нэгж')->value('branch_id'));

        // Бүртгэх, хэрэглэгчийн форм: жагсаалтаас сонгох, «＋ Шинэ нэгж нэмэх…»
        $this->actingAs($admin)->get('/computers/create')->assertOk()->assertSee('id="unit" name="unit" data-org-select', false)
            ->assertSee('＋ Шинэ нэгж нэмэх…')->assertSee('Миний нэгж')->assertDontSee('Дарханы нэгж');
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('id="unit" name="unit" data-org-select', false);

        // Ажилтан хэрэглэх эрхгүй
        $this->actingAs(User::factory()->employee()->create())->get('/units')->assertRedirect('/');
    }

    public function test_departments_belong_to_units(): void
    {
        $admin = User::factory()->admin()->create();
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $mch = Unit::create(['branch_id' => $admin->branch_id, 'name' => 'МЧ']);
        $farUnit = Unit::create(['branch_id' => $far->id, 'name' => 'Дарханы нэгж']);

        // Алба нэмэхдээ нэгж сонгоно; өөр салбарын нэгж болохгүй
        $this->actingAs($admin)->post(route('departments.store'), ['name' => 'МТ алба', 'unit_id' => $mch->id])->assertSessionHasNoErrors();
        $it = \App\Models\Department::where('name', 'МТ алба')->firstOrFail();
        $this->assertSame($mch->id, $it->unit_id);
        $this->actingAs($admin)->post(route('departments.store'), ['name' => 'Х алба', 'unit_id' => $farUnit->id])->assertSessionHasErrors('unit_id');

        // Нэгжгүй алба, дараа нь нэгжид оноох / гаргах (нэр өөрчлөгдөхгүй), түүхэнд нэгжийн нэрээр
        $this->actingAs($admin)->post(route('departments.store'), ['name' => 'Захиргааны алба'])->assertSessionHasNoErrors();
        $adm = \App\Models\Department::where('name', 'Захиргааны алба')->firstOrFail();
        $this->assertNull($adm->unit_id);
        $this->actingAs($admin)->put(route('departments.update', $adm), ['unit_id' => $mch->id])->assertSessionHasNoErrors();
        $this->assertSame([$mch->id, 'Захиргааны алба'], [$adm->fresh()->unit_id, $adm->fresh()->name]);
        $this->assertTrue(ActivityLog::where('subject_id', $adm->id)->where('action', 'updated')->get()
            ->contains(fn ($l) => ($l->changes['unit']['new'] ?? null) === 'МЧ'));
        $this->actingAs($admin)->put(route('departments.update', $adm), ['unit_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($adm->fresh()->unit_id);

        // Хуудас: нэгжээр бүлэглэнэ, нэгжид алба тоо
        $this->actingAs($admin)->get('/departments')->assertOk()->assertSeeInOrder(['МЧ', 'МТ алба', 'Нэгжгүй', 'Захиргааны алба']);
        $this->actingAs($admin)->get('/units')->assertOk()->assertSee('1 алба');

        // Төхөөрөмж: алба сонгоод нэгж хоосон бол албын нэгж бөглөгдөнө; өөр нэгж сонгосон бол хэвээр
        $pc = Computer::factory()->create(['department' => 'МТ алба', 'unit' => null, 'branch_id' => $admin->branch_id]);
        $this->assertSame(['МЧ', $mch->id], [$pc->unit, $pc->unit_id]);
        $other = Computer::factory()->create(['department' => 'МТ алба', 'unit' => 'Өөр нэгж', 'branch_id' => $admin->branch_id]);
        $this->assertSame('Өөр нэгж', $other->unit);

        // Формоос шинэ алба: сонгосон нэгжид харьяалагдана
        $this->actingAs($admin)->post('/computers', ['type' => 'desktop', 'status' => 'new', 'categories' => ['monitor'], 'unit' => 'МЧ', 'department' => 'Санхүүгийн алба'])
            ->assertSessionHasNoErrors();
        $this->assertSame($mch->id, \App\Models\Department::where('name', 'Санхүүгийн алба')->value('unit_id'));

        // Форм: алба сонголтод нэгжийн нэр (JS шүүлтэд)
        $this->actingAs($admin)->get('/computers/create')->assertOk()->assertSee('data-unit="МЧ"', false);

        // Нэгж нэгтгэхэд албууд шинэ нэгжид; нэгж устгахад алба нэгжгүй
        $hq = Unit::create(['branch_id' => $admin->branch_id, 'name' => 'Төв']);
        $this->actingAs($admin)->post(route('units.merge', $mch), ['target_id' => $hq->id])->assertSessionHasNoErrors();
        $this->assertSame($hq->id, $it->fresh()->unit_id);
        Computer::query()->update(['unit_id' => null, 'unit' => null]);
        $this->actingAs($admin)->delete(route('units.destroy', $hq))->assertSessionHasNoErrors();
        $this->assertNull($it->fresh()->unit_id);
    }

    public function test_department_learns_unit_from_registrations(): void
    {
        $admin = User::factory()->admin()->create();
        $dept = \App\Models\Department::create(['branch_id' => $admin->branch_id, 'name' => 'Санхүү']);

        // Нэгжгүй албыг «МЧ» нэгжтэй хамт бүртгэхэд алба «МЧ»-д харьяалагдана
        Computer::factory()->create(['department' => 'Санхүү', 'unit' => 'МЧ', 'branch_id' => $admin->branch_id]);
        $this->assertSame('МЧ', $dept->fresh()->unit?->name);
        // Дараагийнх нь өөр нэгжтэй байсан ч өөрчлөгдөхгүй
        Computer::factory()->create(['department' => 'Санхүү', 'unit' => 'Өөр', 'branch_id' => $admin->branch_id]);
        $this->assertSame('МЧ', $dept->fresh()->unit?->name);

        // Шилжилт: хуучин бүртгэлээс олонхын нэгжийг онооно, тэнцүү бол үгүй
        $it = \App\Models\Department::create(['branch_id' => $admin->branch_id, 'name' => 'МТ']);
        $tie = \App\Models\Department::create(['branch_id' => $admin->branch_id, 'name' => 'Тэнцүү']);
        $a = Unit::create(['branch_id' => $admin->branch_id, 'name' => 'А']);
        $b = Unit::create(['branch_id' => $admin->branch_id, 'name' => 'Б']);
        foreach ([[$it, $a], [$it, $a], [$it, $b], [$tie, $a], [$tie, $b]] as [$d, $u]) {
            \Illuminate\Support\Facades\DB::table('computers')->insert(['name' => 'x', 'type' => 'other', 'status' => 'active', 'inventory_number' => uniqid('X', true),
                'branch_id' => $admin->branch_id, 'department' => $d->name, 'department_id' => $d->id, 'unit' => $u->name, 'unit_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        (require database_path('migrations/2026_11_10_000100_link_departments_to_units_from_data.php'))->up();
        $this->assertSame([$a->id, null], [$it->fresh()->unit_id, $tie->fresh()->unit_id]);
    }

    public function test_departments_page_filters_by_unit_branch_and_name(): void
    {
        $super = User::factory()->superadmin()->create(['branch_id' => null]);
        $admin = User::factory()->admin()->create();
        $far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $mch = Unit::create(['branch_id' => $admin->branch_id, 'name' => 'МЧ']);
        \App\Models\Department::create(['branch_id' => $admin->branch_id, 'unit_id' => $mch->id, 'name' => 'Санхүү']);
        \App\Models\Department::create(['branch_id' => $admin->branch_id, 'name' => 'Захиргаа']);
        \App\Models\Department::create(['branch_id' => $far->id, 'name' => 'Дарханы алба']);

        $this->actingAs($super)->get('/departments')->assertOk()->assertSee('+ Алба нэмэх')->assertSee('Албаны нэрээр хайх')
            ->assertSee('Санхүү')->assertSee('Захиргаа')->assertSee('Дарханы алба');
        $this->actingAs($super)->get('/departments?unit='.$mch->id)->assertOk()->assertSee('Санхүү')->assertDontSee('>Захиргаа<', false)->assertDontSee('Дарханы алба');
        $this->actingAs($super)->get('/departments?unit=none')->assertOk()->assertSee('Захиргаа')->assertDontSee('>Санхүү<', false);
        $this->actingAs($super)->get('/departments?branch='.$far->id)->assertOk()->assertSee('Дарханы алба')->assertDontSee('>Санхүү<', false);
        $this->actingAs($super)->get('/departments?q=Захир')->assertOk()->assertSee('Захиргаа')->assertDontSee('>Санхүү<', false);
        $this->actingAs($super)->get('/departments?q=байхгүй')->assertOk()->assertSee('Шүүлтэд тохирох алба алга.');
        // Админ: салбарын шүүлтүүргүй, өөр салбарынх харагдахгүй
        $this->actingAs($admin)->get('/departments?branch='.$far->id)->assertOk()->assertDontSee('Дарханы алба')->assertDontSee('Бүх салбар');
    }

    public function test_registration_with_new_unit(): void
    {
        $admin = User::factory()->admin()->create();
        $base = ['type' => 'desktop', 'status' => 'new'];

        // Нэг төхөөрөмж: шинэ нэгж бичихэд жагсаалтад нэмэгдэнэ
        $this->actingAs($admin)->post('/computers', $base + ['categories' => ['monitor'], 'unit' => 'Санхүүгийн нэгж'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('units', ['name' => 'Санхүүгийн нэгж', 'branch_id' => $admin->branch_id]);
        $this->assertNotNull(Computer::where('unit', 'Санхүүгийн нэгж')->value('unit_id'));

        // «＋ Шинэ нэгж нэмэх…» сонгоод нэр бичээгүй бол хоосон (нэг ба олон төхөөрөмж)
        $this->actingAs($admin)->post('/computers', $base + ['categories' => ['monitor'], 'name' => 'MON-2', 'unit' => '__new__'])->assertSessionHasNoErrors();
        $this->assertNull(Computer::where('name', 'MON-2')->value('unit'));
        $this->actingAs($admin)->post('/computers/multi', $base + ['unit' => '__new__', 'items' => [['category' => 'processor'], ['category' => 'monitor']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Unit::where('name', '__new__')->count());

        // Олон төхөөрөмж: сонгосон нэгж бүгдэд
        $this->actingAs($admin)->post('/computers/multi', $base + ['unit' => 'Санхүүгийн нэгж', 'items' => [['category' => 'processor'], ['category' => 'monitor']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, Computer::where('unit', 'Санхүүгийн нэгж')->whereNotNull('unit_id')->count());
        $this->assertSame(1, Unit::count());

        // Шинэ ажилтан: нэгж хэрэглэгч дээр ч холбогдоно
        $this->actingAs($admin)->post('/users', ['name' => 'Сараа', 'username' => 'saraa', 'role' => 'employee', 'is_active' => 1,
            'password' => 'password123', 'password_confirmation' => 'password123', 'unit' => 'Санхүүгийн нэгж']);
        $this->assertSame(Unit::value('id'), User::where('username', 'saraa')->value('unit_id'));
    }
}
