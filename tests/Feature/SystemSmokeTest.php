<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AssetRequest;
use App\Models\Branch;
use App\Models\Computer;
use App\Models\ComputerTransfer;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Системийн бүх route-ыг автоматаар тойрч шалгана (smoke test):
 *  - гурван эрхээр бүх GET хуудас 500 алдаагүй нээгдэх, хандах эрхгүй нь хаагдах;
 *  - нэвтрээгүй хэрэглэгч login руу шилжих;
 *  - бүх POST/PUT/PATCH/DELETE route хоосон эсвэл буруу өгөгдөлд 500 өгөхгүй байх;
 *  - хайлт, шүүлтүүрт хачин утга (SQL, массив, урт мөр, unicode) өгөхөд унахгүй байх;
 *  - байхгүй ID-д 404 буцаах;
 *  - Dark/Light горимын товч, скрипт хуудсанд байх.
 */
class SystemSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private User $admin;
    private User $employee;
    private array $params;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = User::factory()->superadmin()->create();
        $this->admin = User::factory()->admin()->create();
        $this->employee = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();

        $pc = Computer::factory()->create(['category' => 'processor', 'status' => 'active', 'assigned_user_id' => $this->employee->id]);
        $free = Computer::factory()->create(['category' => 'processor', 'status' => 'in_storage']);

        $req = AssetRequest::create(['user_id' => $this->employee->id, 'type' => 'issue', 'computer_id' => $pc->id, 'description' => 'Асахгүй']);
        $pwd = PasswordResetRequest::create(['user_id' => $other->id, 'status' => 'pending']);
        $tr = ComputerTransfer::create(['computer_id' => $free->id, 'from_user_id' => $other->id, 'status' => 'pending', 'destination' => 'user']);
        $log = ActivityLog::query()->first() ?? ActivityLog::create(['action' => 'created', 'description' => 'smoke']);

        $this->params = [
            'computer' => $pc->id, 'id' => $pc->id, 'user' => $this->employee->id, 'branch' => $this->admin->branch_id ?? Branch::first()->id,
            'assetRequest' => $req->id, 'passwordRequest' => $pwd->id, 'transfer' => $tr->id, 'log' => $log->id, 'token' => 'smoke-token',
        ];
    }

    /** Апп-ын (vendor биш) route-ууд */
    private function appRoutes(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), function (RouteDef $r) {
            $action = $r->getActionName();

            return ! str_starts_with($r->uri(), '_') && ! str_starts_with($r->uri(), 'storage')
                && ! str_starts_with($r->uri(), 'up') && ! str_contains($action, 'Illuminate\\') ;
        }));
    }

    private function url(RouteDef $r, array $override = []): string
    {
        $p = $override + $this->params;

        return '/'.ltrim(preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($p[$m[1]] ?? 1), $r->uri()), '/');
    }

    private function gets(): array
    {
        return array_filter($this->appRoutes(), fn (RouteDef $r) => in_array('GET', $r->methods()));
    }

    // ─────────────────────────── GET хуудсууд ───────────────────────────

    public function test_every_get_route_opens_without_server_error_for_each_role(): void
    {
        $failures = [];
        foreach (['super' => $this->super, 'admin' => $this->admin, 'employee' => $this->employee] as $role => $user) {
            foreach ($this->gets() as $r) {
                $url = $this->url($r);
                $status = $this->actingAs($user)->get($url)->getStatusCode();
                if ($status >= 500) {
                    $failures[] = "$role GET $url → $status";
                }
            }
        }
        $this->assertSame([], $failures, "500 алдаа:\n".implode("\n", $failures));
    }

    public function test_superadmin_gets_200_on_all_main_pages(): void
    {
        $skip = ['logout', 'reset-password/{token}', 'forgot-password', 'login', 'computers/resolve', 'my/notifications/{id}', 'computers/bulk-create'];
        $bad = [];
        foreach ($this->gets() as $r) {
            if (in_array($r->uri(), $skip) || str_starts_with($r->uri(), 'my/computers')) {
                continue;
            }
            $status = $this->actingAs($this->super)->get($this->url($r))->getStatusCode();
            if ($status !== 200) {
                $bad[] = 'GET '.$this->url($r)." → $status";
            }
        }
        $this->assertSame([], $bad, "Супер админд нээгдээгүй хуудас:\n".implode("\n", $bad));
    }

    public function test_employee_is_blocked_from_every_management_page(): void
    {
        $own = ['/', '/my/computers', '/profile', '/my/notifications', '/my/password', '/login', '/forgot-password'];
        $leaks = [];
        foreach ($this->gets() as $r) {
            $url = $this->url($r);
            if (in_array($url, $own) || str_starts_with($url, '/my/') || str_starts_with($url, '/reset-password')) {
                continue;
            }
            if ($this->actingAs($this->employee)->get($url)->getStatusCode() === 200) {
                $leaks[] = $url;
            }
        }
        $this->assertSame([], $leaks, "Ажилтанд нээгдэх ёсгүй хуудас нээгдэв:\n".implode("\n", $leaks));
    }

    public function test_admin_cannot_open_superadmin_only_pages(): void
    {
        $this->actingAs($this->admin)->get('/branches')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_every_protected_page(): void
    {
        $public = ['login', 'forgot-password', 'reset-password/{token}'];
        $open = [];
        foreach ($this->gets() as $r) {
            if (in_array($r->uri(), $public)) {
                continue;
            }
            $res = $this->get($this->url($r));
            if ($res->getStatusCode() === 200) {
                $open[] = $this->url($r);
            }
        }
        $this->assertSame([], $open, "Нэвтрээгүй хүнд нээлттэй хуудас:\n".implode("\n", $open));
        $this->get('/computers')->assertRedirect('/login');
    }

    // ─────────────────────── Бичих үйлдлүүд (POST/PUT/DELETE) ───────────────────────

    public function test_every_write_route_rejects_empty_or_garbage_input_without_server_error(): void
    {
        $failures = [];
        $writes = array_filter($this->appRoutes(), fn (RouteDef $r) => array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $r->methods()));

        foreach (['super' => $this->super, 'admin' => $this->admin, 'employee' => $this->employee, 'guest' => null] as $role => $user) {
            $arrays = array_fill_keys(['name', 'username', 'email', 'description', 'subject', 'reason', 'note', 'type', 'status', 'q',
                'current_password', 'password_confirmation', 'login', 'recovery_key', 'brand', 'model', 'serial_number', 'price', 'branch_id',
                'department_id', 'unit_id', 'assigned_user_id', 'to_user_id', 'destination', 'role', 'code', 'computer_id', 'ram_gb'], ['x' => ['y']]);
            foreach ([[], ['name' => str_repeat('я', 5000), 'status' => 'xxx', 'ids' => 'not-array', 'computer_id' => 999999, 'password' => ['a']], $arrays] as $i => $payload) {
                foreach ($writes as $r) {
                    $method = strtolower(collect($r->methods())->first(fn ($m) => $m !== 'HEAD'));
                    $url = $this->url($r);
                    if ($url === '/logout') {
                        continue;
                    }
                    $req = $user ? $this->actingAs($user) : $this;
                    $status = $req->$method($url, $payload)->getStatusCode();
                    if ($status >= 500) {
                        $failures[] = "$role ".strtoupper($method)." $url (payload #$i) → $status";
                    }
                    if ($user === null) {
                        $this->app['auth']->forgetGuards();
                    }
                }
            }
        }
        $this->assertSame([], $failures, "500 алдаа:\n".implode("\n", $failures));
    }

    // ─────────────────────── Хайлт, шүүлтүүр, буруу оролт ───────────────────────

    public function test_search_and_filters_survive_hostile_input(): void
    {
        $hostile = [
            ['q' => "' OR 1=1 --"], ['q' => '%_%'], ['q' => str_repeat('a', 3000)], ['q' => '<script>alert(1)</script>'],
            ['q' => ['array']], ['q' => '😀 Монгол ӨҮ'], ['status' => 'nonexistent'], ['status' => ['x']],
            ['branch' => 'abc'], ['branch_id' => -1], ['category' => '../../etc'], ['sort' => 'password'], ['dir' => 'sideways'],
            ['page' => 999999], ['page' => -5], ['page' => 'abc'], ['from' => 'not-a-date', 'to' => '2026-13-45'], ['per_page' => 100000],
        ];
        $pages = ['/', '/computers', '/computers/trash', '/requests', '/repairs', '/transfers', '/activity', '/users', '/departments', '/units', '/password-requests', '/computers/export'];
        $failures = [];
        foreach ($pages as $page) {
            foreach ($hostile as $query) {
                $res = $this->actingAs($this->super)->get($page.'?'.http_build_query($query));
                if ($res->getStatusCode() >= 500) {
                    $failures[] = "$page?".urldecode(http_build_query($query)).' → '.$res->getStatusCode();
                }
            }
        }
        foreach ([['q' => "' OR 1=1 --"], ['q' => ['x']], ['page' => 'abc']] as $query) {
            foreach (['/', '/my/computers'] as $page) {
                if (($s = $this->actingAs($this->employee)->get($page.'?'.http_build_query($query))->getStatusCode()) >= 500) {
                    $failures[] = "employee $page → $s";
                }
            }
        }
        $this->assertSame([], $failures, "500 алдаа:\n".implode("\n", $failures));
    }

    public function test_search_does_not_leak_through_sql_wildcards(): void
    {
        // "' OR 1=1 --" хайлт бүх төхөөрөмжийг буцаах ёсгүй (SQL injection биш, энгийн текст гэж хайна)
        $this->actingAs($this->super)->get('/computers?q='.urlencode("' OR 1=1 --"))
            ->assertOk()->assertDontSee($this->params['computer'] ? Computer::find($this->params['computer'])->registration_number ?? '#never#' : '#never#');
    }

    public function test_xss_in_search_is_escaped(): void
    {
        $this->actingAs($this->super)->get('/computers?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_missing_records_return_404_not_500(): void
    {
        foreach (['/computers/999999', '/computers/999999/edit', '/computers/999999/label', '/computers/999999/print', '/computers/999999/qr.png', '/users/999999/edit'] as $url) {
            $this->actingAs($this->super)->get($url)->assertNotFound();
        }
        $this->actingAs($this->super)->get('/computers/abc')->assertNotFound();
        $this->actingAs($this->employee)->get('/my/computers/999999')->assertNotFound();
    }

    public function test_employee_cannot_open_someone_elses_device(): void
    {
        $foreign = Computer::factory()->create(['category' => 'processor', 'status' => 'active']);
        $status = $this->actingAs($this->employee)->get("/my/computers/{$foreign->id}")->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }

    // ─────────────────────────── Excel татах ───────────────────────────

    public function test_export_is_a_formatted_xlsx_workbook(): void
    {
        Computer::factory()->create(['name' => '=HYPERLINK("http://evil")', 'category' => 'processor', 'purchase_price' => 1500000]);

        $res = $this->actingAs($this->super)->get('/computers/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $res->headers->get('Content-Disposition'));

        $file = $res->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $file);
        foreach (['xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'Бүртгэлийн дугаар',
            'Төхөөрөмжийн жагсаалт', 'Техникийн үзүүлэлт', '<autoFilter ref="A4:', 'state="frozen"', '<f>SUM(L5:',
            'formatCode="yyyy-mm-dd"', 'formatCode="#,##0"'] as $needle) {
            $this->assertStringContainsString($needle, $file);
        }
        // «=»-ээр эхэлсэн текст томьёо биш, энгийн текст (inlineStr) болж орно
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=HYPERLINK(&quot;http://evil&quot;)</t>', $file);
        $this->assertStringNotContainsString('<f>HYPERLINK', $file);

        // Шүүлтүүрийг дагана, хоосон үр дүнд ч эвдрэхгүй
        $this->actingAs($this->super)->get('/computers/export?q=ҮГҮЙ-ТӨХӨӨРӨМЖ')->assertOk();
    }

    // ─────────────────────────── Гацахгүй байх ───────────────────────────

    public function test_mail_notifications_are_sent_after_the_response_in_web_requests(): void
    {
        // Вэб хүсэлт (console биш) байх үеийг дуурайна
        $prop = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $prop->setValue($this->app, false);

        $pc = Computer::find($this->params['computer']);
        $notifications = [
            new \App\Notifications\RepairStatusChanged($pc, 'started'),
            new \App\Notifications\PasswordResetRequested(PasswordResetRequest::find($this->params['passwordRequest'])),
            new \App\Notifications\TemporaryPasswordIssued('Temp12345678'),
        ];
        foreach ($notifications as $n) {
            $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $n);
            $this->assertSame(['mail' => 'deferred', 'database' => 'sync'], $n->viaConnections(), $n::class);
        }

        $prop->setValue($this->app, true);
        $this->assertSame('sync', $notifications[0]->viaConnections()['mail']); // artisan команд, тестэд шууд
    }

    public function test_responses_carry_content_length_so_browser_does_not_wait(): void
    {
        $res = $this->get('/login')->assertOk();
        $this->assertSame((string) strlen($res->getContent()), $res->headers->get('Content-Length'));

        $redirect = $this->actingAs($this->super)->post('/logout');
        $this->assertTrue($redirect->headers->has('Content-Length'));

        // Урсгал хариу (Excel) хэмжээгүй хэвээр
        $this->assertFalse($this->actingAs($this->super)->get('/computers/export')->headers->has('Content-Length'));
    }

    // ─────────────────────────── Dark / Light горим ───────────────────────────

    public function test_theme_script_and_toggle_are_on_app_and_guest_pages(): void
    {
        foreach (['/', '/computers', '/profile'] as $url) {
            $this->actingAs($this->super)->get($url)->assertOk()
                ->assertSee('window.toggleTheme', false)->assertSee('onclick="toggleTheme()"', false);
        }
        $this->actingAs($this->employee)->get('/my/computers')->assertOk()->assertSee('onclick="toggleTheme()"', false);

        $this->app['auth']->forgetGuards();
        foreach (['/login', '/forgot-password'] as $url) {
            $this->get($url)->assertOk()->assertSee('window.toggleTheme', false)->assertSee('onclick="toggleTheme()"', false);
        }
    }

    public function test_printable_pages_stay_light(): void
    {
        $id = $this->params['computer'];
        foreach (["/computers/$id/print", "/computers/$id/label"] as $url) {
            $this->actingAs($this->super)->get($url)->assertOk()->assertDontSee('toggleTheme', false);
        }
    }
}
