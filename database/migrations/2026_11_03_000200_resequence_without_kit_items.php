<?php

use App\Models\Computer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Иж бүрдлийн дагалдах хэрэгсэл (процессортой хамт бүртгэсэн mouse, keyboard ...) жагсаалтад харагдахгүй тул
     * дэс дугаараас чөлөөлж, бусдыг нь завсаргүй 1, 2, 3 ... болгоно.
     */
    public function up(): void
    {
        Computer::resequence();
    }

    public function down(): void
    {
        //
    }
};
