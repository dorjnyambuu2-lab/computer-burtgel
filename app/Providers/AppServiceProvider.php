<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Нэвтрэх бүрд "сүүлд идэвхтэй байсан" огноог шинэчилнэ
        Event::listen(Login::class, function (Login $event) {
            User::whereKey($event->user->getAuthIdentifier())
                ->update(['last_login_at' => now(), 'last_seen_at' => now(), 'is_online' => true]);

            // Ажилтан өөрийн компьютероос нэвтэрвэл тэр компьютерийн IP, MAC-г бүртгэлд автоматаар бичнэ.
            // Сүлжээ шалгахад алдаа гарсан ч нэвтрэлтэд нөлөөлөхгүй.
            if ($event->user instanceof User) {
                rescue(fn () => \App\Support\ClientNetwork::syncForEmployee($event->user, request()), null, report: true);
            }
        });

        // «Гарах» дарвал шууд офлайн болно
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                User::whereKey($event->user->getAuthIdentifier())->update(['last_seen_at' => now(), 'is_online' => false]);
            }
        });

        // Супер админы нууц үг сэргээх имэйл (монголоор)
        ResetPassword::toMailUsing(function (User $user, string $token) {
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            return (new MailMessage)
                ->subject('Нууц үг сэргээх · '.config('app.name'))
                ->greeting('Сайн байна уу, '.$user->name.'!')
                ->line('Таны бүртгэлийн нууц үгийг сэргээх хүсэлт ирлээ.')
                ->action('Нууц үг сэргээх', route('password.reset', ['token' => $token, 'email' => $user->email]))
                ->line('Холбоос '.$minutes.' минутын дараа хүчингүй болно. Сэргээхэд сэргээх түлхүүр шаардлагатай.')
                ->line('Хэрэв та хүсэлт илгээгээгүй бол энэ имэйлийг үл тоомсорлож, системийн аюулгүй байдлыг шалгана уу.')
                ->salutation(config('app.name'));
        });
    }
}