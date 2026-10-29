<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Үйлдлийн түүх»-ийг Супер админ, Админ эрхээр бүх шүүлтүүрийн хослол, буруу утга, хуудаслалт,
 * устгах, сэргээх, хориотой үйлдлээр шалгана. Админд Супер админы болон өөр салбарын юу ч харагдахгүй.
 */
class ActivityHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    private User $admin;

    private User $emp;

    private Branch $far;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->superadmin()->create(['name' => 'Супер']);
        $this->admin = User::factory()->admin()->create(['name' => 'Админ']);
        $this->emp = User::factory()->employee()->create(['name' => 'Ажилтан']);
        $this->far = Branch::create(['name' => 'Дархан', 'code' => 'DRH']);
        $farAdmin = User::factory()->admin()->create(['name' => 'ДарханАдмин', 'branch_id' => $this->far->id]);

        // Төрөл бүрийн түүх: Админ, Супер админ, өөр салбарын Админ, нэвтрэлт, хэлтэс, салбар, устгах
        $this->actingAs($this->admin);
        $mine = Computer::factory()->create(['name' => 'Админы-PC', 'department' => 'МТ алба']);
        $mine->update(['name' => 'Админы-PC-2']);
        $this->actingAs($this->super);
        Computer::factory()->create(['name' => 'Суперын-PC', 'branch_id' => $this->admin->branch_id])->delete();
        Branch::create(['name' => 'Эрдэнэт', 'code' => 'ERD']);
        $this->actingAs($farAdmin);
        Computer::factory()->create(['name' => 'Дарханы-PC', 'branch_id' => $this->far->id]);
        ActivityLog::forUser($this->emp, 'login');
        ActivityLog::forUser($farAdmin, 'login');
        auth()->guard()->logout();
        Computer::factory()->count(25)->create(['branch_id' => $this->admin->branch_id]); // хуудаслалтад (Систем)
    }

    /** Шүүлтүүрийн бүх хослол: 200, Админд Супер админы / өөр салбарын бичлэг үгүй */
    public function test_every_filter_combination_for_both_roles(): void
    {
        $types = ['', ...array_keys(ActivityLog::SUBJECT_TYPES), 'Буруу\\Төрөл'];
        $actions = ['', ...array_keys(ActivityLog::ACTIONS), 'буруу'];
        $queries = ['', 'PC', '#1', '#9999', '%', "'; drop table users; --"];
        $dates = [[], ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()], ['from' => 'abc', 'to' => '2026-99-99']];
        $users = ['', (string) $this->super->id, 'abc'];

        foreach (['super' => $this->super, 'admin' => $this->admin] as $role => $who) {
            foreach ($types as $type) {
                foreach ($actions as $action) {
                    $params = array_filter(['type' => $type, 'action' => $action]);
                    $this->check($who, $role, $params);
                }
            }
            foreach ($queries as $q) {
                foreach ($dates as $d) {
                    foreach ($users as $u) {
                        $this->check($who, $role, array_filter(['q' => $q, 'user_id' => $u] + $d));
                    }
                }
            }
            // Хуудаслалт
            $this->check($who, $role, ['page' => 2]);
            $this->check($who, $role, ['page' => 999]);
        }
    }

    private function check(User $who, string $role, array $params): void
    {
        $res = $this->actingAs($who)->get('/activity?'.http_build_query($params));
        $res->assertOk();
        if ($role !== 'admin') {
            return;
        }
        $logs = $res->viewData('logs');
        foreach ($logs as $log) {
            $this->assertNotSame($this->super->id, $log->user_id, 'Админд Супер админы үйлдэл: '.json_encode($params));
            $this->assertStringNotContainsString('Дархан', (string) $log->subject_label, 'Админд өөр салбарын: '.json_encode($params));
            $this->assertNotSame(Branch::class, $log->subject_type, 'Админд салбарын түүх: '.json_encode($params));
        }
    }

    public function test_detail_dialog_and_labels_render(): void
    {
        $html = $this->actingAs($this->super)->get('/activity')->assertOk()->getContent();
        $this->assertStringContainsString('data-log-detail', $html);           // дэлгэрэнгүй цонхны агуулга
        $this->assertStringContainsString('Салбар', $html);                     // төрлийн тэмдэг
        $this->assertStringContainsString('Нэвтэрсэн', $html);
        $this->assertStringNotContainsString('assigned_user_id', $html);
        $this->assertStringNotContainsString('department_id', $html);
    }

    public function test_superadmin_delete_and_restore_admin_forbidden(): void
    {
        $log = ActivityLog::where('action', 'deleted')->firstOrFail();
        $computerId = $log->subject_id;

        // Админ: түүх устгах, түүхээс сэргээх хориотой
        $this->actingAs($this->admin)->delete('/activity', ['ids' => [$log->id], 'password' => 'password'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('activity.restore', $log))->assertForbidden();

        // Супер админ: сэргээх (архиваас)
        $this->actingAs($this->super)->post(route('activity.restore', $log))->assertSessionHasNoErrors();
        $this->assertNull(Computer::withTrashed()->find($computerId)->deleted_at);

        // Супер админ: буруу нууц үгээр устгахгүй, зөвөөр устгана, «Түүх устгасан» үлдэнэ
        $target = ActivityLog::where('action', 'login')->firstOrFail();
        $this->actingAs($this->super)->delete('/activity', ['ids' => [$target->id], 'password' => 'буруу']);
        $this->assertNotNull($target->fresh());
        $this->actingAs($this->super)->delete('/activity', ['ids' => [$target->id], 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNull($target->fresh());
        $this->assertTrue(ActivityLog::where('action', 'logs_deleted')->exists());
        // Ажилтан хуудас руу орохгүй
        $this->actingAs($this->emp)->get('/activity')->assertRedirect('/');
    }

    public function test_department_and_user_subject_logs_render_after_subject_deleted(): void
    {
        // Устгагдсан хэрэглэгч, хэлтсийн түүх: нэр нь хадгалагдсан хэвээр, хуудас алдаагүй
        $dept = Department::create(['branch_id' => $this->admin->branch_id, 'name' => 'Түр алба']);
        $this->actingAs($this->super)->delete(route('departments.destroy', $dept));
        $gone = User::factory()->employee()->create(['name' => 'Гарсан']);
        $this->actingAs($this->super)->delete("/users/{$gone->id}");

        $this->actingAs($this->super)->get('/activity?type='.urlencode(Department::class))->assertOk()->assertSee('Түр алба');
        $this->actingAs($this->super)->get('/activity?type='.urlencode(User::class).'&action=deleted')->assertOk()->assertSee('Гарсан');
    }
}
