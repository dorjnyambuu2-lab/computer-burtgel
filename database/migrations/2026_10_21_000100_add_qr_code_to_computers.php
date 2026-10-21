<?php

use App\Models\Computer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Төхөөрөмж бүрийн давтагдашгүй QR дугаар (10 оронтой тоо). QR код энэ дугаараар үүснэ.
     * Бүртгэлийн дугаар өөрчлөгдсөн ч QR дугаар өөрчлөгдөхгүй тул наасан шошго хүчинтэй хэвээр байна.
     */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->string('qr_code', 20)->nullable()->unique()->after('inventory_number');
        });

        // Одоо байгаа (устгагдсан ч) бүх төхөөрөмжид дугаар олгоно
        DB::table('computers')->whereNull('qr_code')->orderBy('id')->pluck('id')
            ->each(fn ($id) => DB::table('computers')->where('id', $id)->update(['qr_code' => Computer::newQrCode()]));
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropUnique(['qr_code']);
            $table->dropColumn('qr_code');
        });
    }
};
