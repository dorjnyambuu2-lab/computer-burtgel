<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Компьютер, төхөөрөмжийн худалдан авсан үнэ (₮)
        Schema::table('computers', function (Blueprint $table) {
            $table->decimal('purchase_price', 14, 2)->nullable()->after('purchase_date');
        });

        // Засварын зардал (₮): засварт бүртгэх, явц шинэчлэх, дуусгах бүрт оруулж болно. Нийлбэр нь нийт засварын зардал.
        Schema::table('computer_events', function (Blueprint $table) {
            $table->decimal('cost', 14, 2)->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('computer_events', fn (Blueprint $table) => $table->dropColumn('cost'));
        Schema::table('computers', fn (Blueprint $table) => $table->dropColumn('purchase_price'));
    }
};
