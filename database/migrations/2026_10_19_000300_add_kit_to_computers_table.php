<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Иж бүрдэл: үндсэн компьютерийн дугаар (PC-0012) нь иж бүрдлийн код, дагалдах хэрэгслүүд
     * PC-0012-1, PC-0012-2 ... дэд дугаартай. kit_parent_id = үндсэн компьютер, kit_index = дэд дугаар.
     */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->foreignId('kit_parent_id')->nullable()->after('category')->constrained('computers')->nullOnDelete();
            $table->unsignedSmallInteger('kit_index')->nullable()->after('kit_parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kit_parent_id');
            $table->dropColumn('kit_index');
        });
    }
};
