<?php

use App\Models\Computer;
use App\Models\User;
use App\Notifications\RepairQueueDigest;
use App\Support\Notify;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Өгөгдлийн сангийн нөөц: Windows Task Scheduler өдөр бүр шөнө ажиллуулна (config/backup.php)
Artisan::command('db:backup', function () {
    try {
        $file = App\Support\DatabaseBackup::create();
        $this->info('Нөөц үүслээ: '.$file.' ('.round(filesize($file) / 1024, 1).' KB)');
        if ($copy = App\Support\DatabaseBackup::copy($file)) {
            $this->info('Нэмэлт хуулбар: '.$copy);
        }
        $deleted = App\Support\DatabaseBackup::prune();
        if ($deleted) {
            $this->info($deleted.' хуучин нөөц устгалаа (сүүлийн '.config('backup.keep_days').' өдрийнх хадгалагдана).');
        }
        Illuminate\Support\Facades\Log::info('Өгөгдлийн сангийн нөөц үүслээ', ['file' => $file]);
    } catch (\Throwable $e) {
        Illuminate\Support\Facades\Log::error('Өгөгдлийн сангийн нөөц амжилтгүй: '.$e->getMessage());
        $this->error('Нөөц амжилтгүй: '.$e->getMessage());

        return 1;
    }
})->purpose('Өгөгдлийн сангийн нөөц авах');

// Нөөцөөс сэргээх. --database=туршилт гэж өгвөл одоогийн өгөгдлийг хөндөхгүй, тусдаа санд сэргээж шалгана
Artisan::command('db:restore {file? : Нөөц файлын нэр (хоосон бол хамгийн сүүлийнх)} {--database= : Сэргээх өгөгдлийн сан}', function () {
    $files = App\Support\DatabaseBackup::list();
    if (! $files) {
        $this->error('Нөөц олдсонгүй: '.config('backup.path'));

        return 1;
    }

    $name = $this->argument('file');
    $file = $name ? collect($files)->first(fn ($f) => basename($f) === basename($name)) : $files[0];
    if (! $file) {
        $this->error('Ийм нөөц олдсонгүй. Байгаа нөөцүүд:');
        collect($files)->take(10)->each(fn ($f) => $this->line('  '.basename($f)));

        return 1;
    }

    $current = config('database.connections.'.config('database.default').'.database');
    $target = $this->option('database') ?: $current;

    if ($target === $current) {
        $this->warn("«{$current}» санг ".basename($file).'-ийн байдал руу буцаана. Одоогийн өгөгдөл солигдоно!');
        if (! $this->confirm('Үргэлжлүүлэх үү?')) {
            return 1;
        }
        // Санамсаргүй сэргээлтээс хамгаалж одоогийн байдлын нөөцийг эхлээд авна
        $this->info('Одоогийн байдлын нөөц: '.App\Support\DatabaseBackup::create());
    }

    try {
        App\Support\DatabaseBackup::restore($file, $target);
    } catch (\Throwable $e) {
        $this->error('Сэргээж чадсангүй: '.$e->getMessage());

        return 1;
    }

    $this->info(basename($file)." → «{$target}» санд сэргээлээ.");
})->purpose('Өгөгдлийн сангийн нөөцөөс сэргээх');

// Засварт байгаа компьютеруудын жагсаалтыг (#1, #2 ...) админ, супер админуудад имэйл + системээр илгээнэ
Artisan::command('repairs:digest {--force : Дараалал хоосон байсан ч илгээх}', function () {
    $queue = Computer::query()->repairQueue()->get();

    if ($queue->isEmpty() && ! $this->option('force')) {
        $this->info('Засварт компьютер алга, тайлан илгээсэнгүй.');

        return;
    }

    // Супер админ бүх дарааллыг, Админ зөвхөн өөрийн салбарынхыг авна
    $staff = User::activeStaff();
    $sent = 0;
    foreach ($staff as $user) {
        $items = $user->isBranchScoped() ? $queue->where('branch_id', $user->branch_id)->values() : $queue;
        if ($items->isEmpty() && ! $this->option('force')) {
            continue;
        }
        Notify::send([$user], new RepairQueueDigest($items));
        $sent++;
    }

    $this->info($queue->count().' компьютерийн жагсаалтыг '.$sent.' хэрэглэгчид илгээлээ.');
})->purpose('Засварын дарааллын тайланг админуудад илгээх');

Schedule::command('repairs:digest')->dailyAt(config('security.repair_digest_time'));

// Сүүлчийн арга: сервер рүү хандах эрхтэй хүн Супер админы нууц үгийг шинэчилнэ
Artisan::command('superadmin:reset-password {username? : Супер админы нэвтрэх нэр}', function () {
    $username = $this->argument('username')
        ?: $this->choice('Супер админ', User::where('role', 'superadmin')->pluck('username')->all());

    $user = User::where('role', 'superadmin')->where('username', $username)->first();
    if (! $user) {
        $this->error('Ийм нэвтрэх нэртэй Супер админ олдсонгүй.');

        return 1;
    }

    $password = $this->secret('Шинэ нууц үг (8+ тэмдэгт)');
    $validator = Validator::make(
        ['password' => $password, 'password_confirmation' => $this->secret('Шинэ нууц үг давтах')],
        ['password' => ['required', 'confirmed', Password::min(8)]]
    );

    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }

    $user->forceFill(['password' => $password, 'must_change_password' => false, 'is_active' => true])->save();
    $this->info($user->username.' хэрэглэгчийн нууц үг шинэчлэгдлээ.');
})->purpose('Супер админы нууц үгийг серверээс сэргээх');
