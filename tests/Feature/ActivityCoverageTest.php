<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «Үйлдлийн түүх»: төхөөрөмжөөс гадна хэрэглэгч, салбар, алба хэлтэс, нэвтрэлт, нууц үг бүртгэгдэнэ */
class ActivityCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function last(string $type, int $id): ActivityLog
    {
        return ActivityLog::where('subject_type', $type)->where('subject_id', $id)->latest('id')->firstOrFail();
    }

    public function test_user_branch_department_login_and_password_are_logged(): void
    {
        $super = User::factory()->superadmin()->create(['username' => 'boss']);

        // Нэвтрэх, гарах
        $this->post('/login', ['login' => 'boss', 'password' => 'password']);
        $this->assertSame('login', $this->last(User::class, $super->id)->action);
        $this->post('/logout');
        $this->assertSame('logout', $this->last(User::class, $super->id)->action);

        // Хэрэглэгч нэмэх, эрх/идэвх солих (нууц үг түүхэнд бичигдэхгүй)
        $this->actingAs($super)->post('/users', ['name' => 'Админ', 'username' => 'adm', 'role' => 'admin', 'branch_id' => Branch::first()->id,
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'is_active' => 1])->assertSessionHasNoErrors();
        $admin = User::where('username', 'adm')->firstOrFail();
        $created = $this->last(User::class, $admin->id);
        $this->assertSame(['created', $super->id], [$created->action, $created->user_id]);
        $this->assertArrayNotHasKey('password', $created->changes);

        $this->actingAs($super)->put("/users/{$admin->id}", ['name' => 'Админ', 'username' => 'adm', 'role' => 'admin', 'branch_id' => $admin->branch_id, 'is_active' => 0])->assertSessionHasNoErrors();
        $active = $this->last(User::class, $admin->id)->changes['is_active'];
        $this->assertSame(['Тийм', 'Үгүй'], [ActivityLog::displayValue('is_active', $active['old']), ActivityLog::displayValue('is_active', $active['new'])]);

        // Админ түр нууц үг олгосон / хэрэглэгч өөрөө сольсон
        $admin->forceFill(['password' => 'Temp12345'])->save();
        $this->assertSame('password_reset', $this->last(User::class, $admin->id)->action);
        $this->actingAs($admin->fresh())->put('/my/password', ['current_password' => 'Temp12345', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123']);
        $this->assertSame('password_changed', $this->last(User::class, $admin->id)->action);

        // Салбар, алба хэлтэс (нэр солих, нэгтгэх)
        $this->actingAs($super)->post('/branches', ['name' => 'Эрдэнэт', 'code' => 'ERD']);
        $branch = Branch::where('code', 'ERD')->firstOrFail();
        $this->assertSame('created', $this->last(Branch::class, $branch->id)->action);

        $pc = Computer::factory()->create(['department' => 'сыы']);
        $typo = $pc->departmentRecord;
        $good = Department::create(['branch_id' => $pc->branch_id, 'name' => 'Санхүүгийн алба']);
        $this->actingAs($super)->post(route('departments.merge', $typo), ['target_id' => $good->id]);
        $merged = $this->last(Department::class, $typo->id);
        $this->assertSame(['merged', 'Санхүүгийн алба', 1], [$merged->action, $merged->changes['merged_into']['new'], $merged->changes['moved_devices']['new']]);

        // Хуудас: төрлийн шүүлтүүр, тэмдэг
        $onlyDepartments = $this->actingAs($super)->get('/activity?type='.urlencode(Department::class))->assertOk()->assertSee('Нэгтгэсэн')->viewData('logs');
        $this->assertSame([Department::class], $onlyDepartments->pluck('subject_type')->unique()->values()->all());
        $this->actingAs($super)->get('/activity')->assertOk()->assertSee('Хэрэглэгч')->assertSee('Нэвтэрсэн');
    }

    public function test_admin_sees_own_branch_users_but_not_other_branch(): void
    {
        $admin = User::factory()->admin()->create();
        $mine = User::factory()->employee()->create(['name' => 'МанайАжилтан']);
        $far = User::factory()->employee()->create(['name' => 'ДарханАжилтан', 'branch_id' => Branch::create(['name' => 'Дархан', 'code' => 'DRH'])->id]);
        ActivityLog::forUser($mine, 'login');
        ActivityLog::forUser($far, 'login');

        $this->actingAs($admin)->get('/activity?type='.urlencode(User::class))->assertOk()
            ->assertSee('МанайАжилтан')->assertDontSee('ДарханАжилтан');
        $this->assertArrayNotHasKey(Branch::class, $this->actingAs($admin)->get('/activity')->viewData('types'));

        // Админ: өөрийн болон салбарын ажилтны үйлдлийг харна, Супер админыхыг (салбарын төхөөрөмж дээр ч) харахгүй
        $super = User::factory()->superadmin()->create();
        $pc = Computer::factory()->create(['branch_id' => $admin->branch_id, 'name' => 'Салбарын-PC']);
        $this->actingAs($super)->put("/computers/{$pc->id}", ['name' => 'СуперЗассан', 'type' => 'desktop', 'status' => 'active',
            'inventory_number' => $pc->inventory_number, 'branch_id' => $admin->branch_id])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/computers/{$pc->id}", ['name' => 'АдминЗассан', 'type' => 'desktop', 'status' => 'active',
            'inventory_number' => $pc->inventory_number])->assertSessionHasNoErrors();

        $page = $this->actingAs($admin)->get('/activity')->assertOk();
        $actors = $page->viewData('logs')->pluck('user_id')->unique();
        $this->assertFalse($actors->contains($super->id), 'Супер админы үйлдэл Админд харагдахгүй');
        $this->assertTrue($actors->contains($admin->id));
        $this->assertFalse($page->viewData('users')->pluck('id')->contains($super->id));
        $this->assertFalse($page->viewData('users')->pluck('id')->contains($far->id));
        // Супер админ бүгдийг харна
        $this->assertTrue($this->actingAs($super)->get('/activity')->viewData('logs')->pluck('user_id')->contains($super->id));
    }
}
