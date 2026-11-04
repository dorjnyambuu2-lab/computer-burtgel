<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * delivered_quantity: захиалгын мөрөөс «Төхөөрөмж олгох»-оор хэдэн ширхэг олгосон (UPS ×2-оос 1 г.м.).
     * warranty_notified_at: баталгаа дуусах сануулга илгээсэн огноо (нэг төхөөрөмжид нэг удаа; огноо солигдвол дахин).
     */
    public function up(): void
    {
        Schema::table('asset_request_items', function (Blueprint $t) {
            $t->unsignedTinyInteger('delivered_quantity')->default(0)->after('quantity');
        });
        Schema::table('computers', function (Blueprint $t) {
            $t->timestamp('warranty_notified_at')->nullable()->after('warranty_until');
        });
    }

    public function down(): void
    {
        Schema::table('asset_request_items', fn (Blueprint $t) => $t->dropColumn('delivered_quantity'));
        Schema::table('computers', fn (Blueprint $t) => $t->dropColumn('warranty_notified_at'));
    }
};
