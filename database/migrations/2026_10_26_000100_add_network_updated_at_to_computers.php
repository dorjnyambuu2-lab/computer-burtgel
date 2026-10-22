<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ажилтан өөрийн компьютероос нэвтрэхэд IP, MAC автоматаар шинэчлэгдсэн сүүлийн огноо */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->timestamp('network_updated_at')->nullable()->after('mac_address');
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropColumn('network_updated_at');
        });
    }
};
