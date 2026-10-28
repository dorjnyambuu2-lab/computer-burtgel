<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Имэйл заавал биш: компьютер бүртгэхэд хариуцагчаар бичсэн шинэ ажилтан автоматаар үүсдэг
     * (имэйл нь мэдэгдэхгүй). Мэдэгдэл имэйлгүй бол зөвхөн системд очно.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable(false)->change();
        });
    }
};
