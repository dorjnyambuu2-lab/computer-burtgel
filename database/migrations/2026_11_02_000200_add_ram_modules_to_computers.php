<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RAM-ын модулиуд: «8GB + 4GB». ram_gb-д нийлбэр (12) хэвээр хадгалагдана (эрэмбэлэх, экспорт).
     * Ганц модультай бол хоосон.
     */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $t) {
            $t->string('ram_modules', 100)->nullable()->after('ram_gb');
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $t) {
            $t->dropColumn('ram_modules');
        });
    }
};
