<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AssetRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComputerActionController;
use App\Http\Controllers\ComputerBulkController;
use App\Http\Controllers\ComputerController;
use App\Http\Controllers\ComputerDetectController;
use App\Http\Controllers\ComputerQrController;
use App\Http\Controllers\ComputerToolsController;
use App\Http\Controllers\ComputerTransferController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\MyComputerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PasswordResetRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Нэвтрээгүй хэрэглэгч
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // Нууц үг мартсан: Супер админ имэйлээр сэргээнэ, бусад нь Супер админ руу хүсэлт илгээнэ
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.store');
});

// Нэвтэрсэн хэрэглэгч
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Бүх эрх (ажилтан ч орно): профайл
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Ажилтан: эвдрэл мэдээлэх, төхөөрөмж захиалах, акт баталгаажуулах
    Route::post('/my/requests', [AssetRequestController::class, 'store'])->name('my.requests.store');
    Route::post('/my/acknowledge', [AssetRequestController::class, 'acknowledge'])->name('my.acknowledge');

    // Ажилтан: нууц үг солих
    Route::get('/my/password', [PasswordController::class, 'edit'])->name('my.password.edit');
    Route::put('/my/password', [PasswordController::class, 'update'])->name('my.password.update');

    // Ажилтан: миний төхөөрөмж, үзүүлэлт харах, хүлээн авсныг баталгаажуулах (шилжүүлэх эрх зөвхөн Супер админ, Админд)
    Route::get('/my/computers', [MyComputerController::class, 'index'])->name('my.computers');
    Route::get('/my/computers/{id}', [MyComputerController::class, 'show'])
        ->whereNumber('id')
        ->name('my.computers.show');
    Route::post('/my/computers/{id}/acknowledge', [MyComputerController::class, 'acknowledge'])->name('my.computers.acknowledge');

    // Бүх эрх: систем доторх мэдэгдлүүд (засварын мэдээлэл гэх мэт)
    Route::get('/my/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/my/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/my/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Төхөөрөмж шилжүүлэх: Супер админ (бүх салбар) ба салбарын Админ (өөрийн салбар); ажилтан эрхгүй
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::get('transfers', [ComputerTransferController::class, 'index'])->name('transfers.index');
        Route::post('transfers', [ComputerTransferController::class, 'store'])->name('transfers.store');
        // Өмнө ажилтнаас ирсэн шийдвэрлэгдээгүй хүсэлт: хүлээн авах (агуулахад) → олгох, эсвэл татгалзах
        Route::post('transfers/{transfer}/receive', [ComputerTransferController::class, 'receive'])->name('transfers.receive');
        Route::post('transfers/{transfer}/issue', [ComputerTransferController::class, 'issue'])->name('transfers.issue');
        Route::post('transfers/{transfer}/reject', [ComputerTransferController::class, 'reject'])->name('transfers.reject');
    });

    // Superadmin + Админ
    Route::middleware('role:superadmin,admin')->group(function () {
        // Ажилтнуудын илгээсэн хүсэлтийг харах, батлах, татгалзах
        Route::get('requests', [AssetRequestController::class, 'index'])->name('requests.index');
        // "Төлөв ба хариу" маягтаас (PATCH) хадгалах
        Route::patch('requests/{assetRequest}', [AssetRequestController::class, 'update'])->name('requests.update');


        // Компьютерийн бүртгэл: харах, хайх, CSV татах, QR шошго, нэмэх/засах/устгах
        // (trash, export нь resource-ийн computers/{computer}-ээс ӨМНӨ байх ёстой)
        Route::get('computers/trash', [ComputerToolsController::class, 'trash'])->name('computers.trash');
        Route::post('computers/{id}/restore', [ComputerToolsController::class, 'restore'])->name('computers.restore');
        Route::get('computers/export', [ComputerController::class, 'export'])->name('computers.export');

        // QR уншуулах (scan, resolve нь computers/{computer}-ээс ӨМНӨ байх ёстой)
        Route::get('computers/scan', [ComputerActionController::class, 'scan'])->name('computers.scan');
        Route::get('computers/resolve', [ComputerActionController::class, 'resolve'])->name('computers.resolve');

        // Ажилтанд олон төхөөрөмж (процессор, дэлгэц, mouse ...) нэг дор олгох
        Route::get('computers/bulk-assign', [ComputerActionController::class, 'bulkForm'])->name('computers.bulk-assign');
        Route::post('computers/bulk-assign', [ComputerActionController::class, 'bulkStore'])->name('computers.bulk-assign.store');

        // «Багцаар бүртгэх» хасагдсан: хуучин холбоос «Шинэ компьютер бүртгэх» рүү (олон төхөөрөмжийг тэнд нэг дор бүртгэнэ)
        Route::get('computers/bulk-create', fn (\Illuminate\Http\Request $request) => redirect()->route('computers.create', $request->query()));
        // "Шинэ компьютер бүртгэх" формоос олон ангилал сонгосон үед
        Route::post('computers/multi', [ComputerBulkController::class, 'storeMultiple'])->name('computers.store-multi');
        // Компьютерийн мэдээллийг автоматаар уншиж бүртгэх скрипт (.bat)
        Route::get('computers/detect-script', [ComputerDetectController::class, 'script'])->name('computers.detect-script');
        // Системийг нээсэн компьютерийн дотоод сүлжээний IP, MAC (формын «IP, MAC автоматаар авах»)

        // Компьютерийн мэдээллийн хуудаснаас: олгох, буцаан авах, засварт бүртгэх
        Route::post('computers/{computer}/assign', [ComputerActionController::class, 'assign'])->name('computers.assign');
        Route::post('computers/{computer}/return', [ComputerActionController::class, 'takeBack'])->name('computers.return');
        Route::post('computers/{computer}/repair', [ComputerActionController::class, 'repair'])->name('computers.repair');

        // Засварын дараалал: #1, #2 ... дарааллаар харах, явц өөрчлөх, дуусгах, байр солих
        Route::get('repairs', [RepairController::class, 'index'])->name('repairs.index');
        Route::patch('repairs/{computer}', [RepairController::class, 'update'])->name('repairs.update');
        Route::post('repairs/{computer}/move', [RepairController::class, 'move'])->name('repairs.move');
        Route::post('requests/{assetRequest}/repair', [RepairController::class, 'fromRequest'])->name('requests.repair');

        Route::get('computers/{computer}/label', [ComputerToolsController::class, 'label'])->name('computers.label');
        Route::get('computers/{computer}/qr.png', [ComputerQrController::class, 'png'])->name('computers.qr-png');

        Route::resource('computers', ComputerController::class);

        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

        // Хэрэглэгч: Супер админ — админ бүртгэнэ; Админ — өөрийн салбарын ажилтан бүртгэнэ (User::canManageUsers/canEditUser)
        Route::resource('users', UserController::class)->except(['show']);
    });

    // Зөвхөн Superadmin
    Route::middleware('role:superadmin')->group(function () {
        // Бүрмөсөн устгах (сэргээх боломжгүй): компьютер, төхөөрөмж болон үйлдлийн түүх. Нууц үгээ дахин оруулна.
        Route::delete('computers/{id}/force', [ComputerToolsController::class, 'forceDelete'])
            ->middleware('throttle:5,1')
            ->name('computers.force-delete');
        Route::delete('activity', [ActivityLogController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('activity.destroy');
        // Үйлдлийн түүхээс устгасан (архивласан эсвэл бүрмөсөн устгасан) компьютер, төхөөрөмжийг сэргээх
        Route::post('activity/{log}/restore', [ActivityLogController::class, 'restore'])->name('activity.restore');

        // Бүх төрлийн админ, хэрэглэгчийг бүртгэх
        // Салбар: салбар бүрийн Админ зөвхөн өөрийн салбарыг удирдана
        Route::resource('branches', \App\Http\Controllers\BranchController::class)->only(['index', 'store', 'update', 'destroy']);

        // Админ / ажилтны нууц үг сэргээх хүсэлтүүд
        Route::get('password-requests', [PasswordResetRequestController::class, 'index'])->name('password-requests.index');
        Route::post('password-requests/{passwordRequest}/resolve', [PasswordResetRequestController::class, 'resolve'])->name('password-requests.resolve');
        Route::post('password-requests/{passwordRequest}/reject', [PasswordResetRequestController::class, 'reject'])->name('password-requests.reject');
    });
});