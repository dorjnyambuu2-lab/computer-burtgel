<?php

namespace Tests\Feature;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\PasswordResetRequested;
use App\Notifications\TemporaryPasswordIssued;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Админ нууц үгээ имэйлийн холбоогоор сэргээх; түр нууц үг имэйлээр очих */
class PasswordEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_email_resets_by_link_without_recovery_key(): void
    {
        Notification::fake();
        config(['security.superadmin_recovery_key' => 'secret-recovery-key']);
        $super = User::factory()->superadmin()->create();
        $admin = User::factory()->admin()->create(['email' => 'admin-real@example.mn']);

        $this->post(route('password.email'), ['login' => $admin->username])->assertSessionHas('status');

        // Холбоос очно, Супер админ руу хүсэлт очихгүй
        $token = null;
        Notification::assertSentTo($admin, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        Notification::assertNotSentTo($super, PasswordResetRequested::class);
        $this->assertSame(0, PasswordResetRequest::count());

        // Сэргээх хуудсанд түлхүүрийн талбаргүй
        $this->get(route('password.reset', ['token' => $token, 'email' => $admin->email]))->assertOk()->assertDontSee('name="recovery_key"', false);

        $this->post(route('password.store'), ['token' => $token, 'email' => $admin->email,
            'password' => 'new-admin-pass', 'password_confirmation' => 'new-admin-pass'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-admin-pass', $admin->fresh()->password));
    }

    public function test_superadmin_still_needs_recovery_key_and_employee_cannot_use_link(): void
    {
        Notification::fake();
        config(['security.superadmin_recovery_key' => 'secret-recovery-key']);
        $super = User::factory()->superadmin()->create();
        $emp = User::factory()->employee()->create(['email' => 'emp@example.mn', 'password' => 'old-pass-123']);

        $this->post(route('password.email'), ['login' => $super->username]);
        $token = null;
        Notification::assertSentTo($super, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->get(route('password.reset', ['token' => $token, 'email' => $super->email]))->assertOk()->assertSee('name="recovery_key"', false);
        $this->post(route('password.store'), ['token' => $token, 'email' => $super->email,
            'password' => 'hacked-pass', 'password_confirmation' => 'hacked-pass'])->assertSessionHasErrors('recovery_key');

        // Ажилтан холбоо авахгүй, хүсэлтээр; шууд сэргээж чадахгүй
        $this->post(route('password.email'), ['login' => $emp->username]);
        Notification::assertNotSentTo($emp, ResetPassword::class);
        $this->assertSame(1, PasswordResetRequest::where('user_id', $emp->id)->count());
        $this->post(route('password.store'), ['token' => 'x', 'email' => $emp->email,
            'recovery_key' => 'secret-recovery-key', 'password' => 'hacked-pass', 'password_confirmation' => 'hacked-pass'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-pass-123', $emp->fresh()->password));
    }

    public function test_temporary_password_is_emailed_when_user_has_email(): void
    {
        Notification::fake();
        $super = User::factory()->superadmin()->create();
        $withEmail = User::factory()->employee()->create(['email' => 'emp@example.mn']);
        $noEmail = User::factory()->employee()->create(['email' => null]);

        $a = PasswordResetRequest::create(['user_id' => $withEmail->id, 'status' => 'pending']);
        $this->actingAs($super)->post(route('password-requests.resolve', $a))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'emp@example.mn'));
        Notification::assertSentTo($withEmail, TemporaryPasswordIssued::class);

        $b = PasswordResetRequest::create(['user_id' => $noEmail->id, 'status' => 'pending']);
        $this->actingAs($super)->post(route('password-requests.resolve', $b))->assertSessionHas('temporary_password');
        Notification::assertNotSentTo($noEmail, TemporaryPasswordIssued::class);
    }
}
