<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperadminResetFromEnvTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::create([
            'name' => 'Супер админ', 'username' => 'superadmin', 'email' => 'sa@example.com',
            'password' => 'old-password-1', 'role' => 'superadmin', 'is_active' => true, 'must_change_password' => false,
        ]);
    }

    public function test_does_nothing_when_variable_is_empty(): void
    {
        $user = $this->superadmin();
        config(['security.superadmin_reset_password' => null]);

        $this->artisan('superadmin:reset-from-env')->assertExitCode(0);

        $user->refresh();
        $this->assertTrue(Hash::check('old-password-1', $user->password));
        $this->assertFalse((bool) $user->must_change_password);
    }

    public function test_resets_password_and_forces_change_on_login(): void
    {
        $user = $this->superadmin();
        config(['security.superadmin_reset_password' => 'superadmin123']);

        $this->artisan('superadmin:reset-from-env')
            ->expectsOutputToContain('нууц үгийг SUPERADMIN_RESET_PASSWORD-оор шинэчиллээ')
            ->doesntExpectOutputToContain('superadmin123')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertTrue(Hash::check('superadmin123', $user->password));
        $this->assertTrue((bool) $user->must_change_password);

        // Нэвтрэхэд нууц үг солих хуудас руу шилжүүлнэ
        $this->post('/login', ['login' => 'superadmin', 'password' => 'superadmin123'])
            ->assertRedirect(route('my.password.edit'));
    }

    public function test_rejects_too_short_password(): void
    {
        $user = $this->superadmin();
        config(['security.superadmin_reset_password' => 'short']);

        $this->artisan('superadmin:reset-from-env')->assertExitCode(1);

        $this->assertTrue(Hash::check('old-password-1', $user->refresh()->password));
    }
}
