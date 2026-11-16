<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/** «Намайг сана»: session дууссаны дараа ч нэвтэрсэн хэвээр, идэвхгүй болгосон хэрэглэгч гарна */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    private function rememberCookie(TestResponse $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                return $cookie;
            }
        }

        return null;
    }

    private function login(User $user, bool $remember): TestResponse
    {
        return $this->post('/login', ['login' => $user->username, 'password' => 'password'] + ($remember ? ['remember' => 'on'] : []));
    }

    /** Хөтөч хаагдсан / session дууссан: зөвхөн «Намайг сана» cookie-тойгоор хандана */
    private function visitWithOnlyRememberCookie(Cookie $cookie, string $url = '/'): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        // Хариуны cookie аль хэдийн шифрлэгдсэн тул дахин шифрлэхгүйгээр илгээнэ
        return $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get($url);
    }

    public function test_without_remember_no_long_lived_cookie_is_set(): void
    {
        $user = User::factory()->employee()->create();

        $this->assertNull($this->rememberCookie($this->login($user, false)));
    }

    public function test_remember_me_keeps_user_logged_in_after_session_ends(): void
    {
        $user = User::factory()->employee()->create();

        $cookie = $this->rememberCookie($this->login($user, true));
        $this->assertNotNull($cookie);
        $this->assertGreaterThan(now()->addDays(300)->timestamp, $cookie->getExpiresTime());

        $this->visitWithOnlyRememberCookie($cookie)->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivated_user_is_logged_out_despite_remember_cookie(): void
    {
        $user = User::factory()->employee()->create();
        $cookie = $this->rememberCookie($this->login($user, true));

        $user->forceFill(['is_active' => false])->saveQuietly();

        $this->visitWithOnlyRememberCookie($cookie)->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest();
        // Гаргах үед токен шинэчлэгдсэн тул хуучин cookie дахин хэзээ ч ажиллахгүй
        $user->forceFill(['is_active' => true])->saveQuietly();
        $this->visitWithOnlyRememberCookie($cookie)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_user_with_open_session_is_logged_out(): void
    {
        $user = User::factory()->employee()->create();
        $this->login($user, false);
        $this->assertAuthenticatedAs($user);

        $user->forceFill(['is_active' => false])->saveQuietly();
        // Бодит хүсэлт шиг хэрэглэгчийг session-аас (баазаас) дахин уншина
        $this->app['auth']->forgetGuards();

        $this->get('/my/computers')->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_deactivating_from_user_form_invalidates_remember_token(): void
    {
        $super = User::factory()->superadmin()->create();
        $emp = User::factory()->employee()->create();
        $cookie = $this->rememberCookie($this->login($emp, true));
        $this->post('/logout');
        $token = $emp->fresh()->getRememberToken();

        $this->app['auth']->forgetGuards();
        $this->actingAs($super)->put("/users/{$emp->id}", [
            'name' => $emp->name, 'username' => $emp->username, 'role' => 'employee', 'branch_id' => $emp->branch_id, 'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($token, $emp->fresh()->getRememberToken());
        $this->visitWithOnlyRememberCookie($cookie)->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
