й<?php

use App\Models\Computer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Дэс дугаарыг идэвхтэй компьютеруудад 1-ээс завсаргүй дахин олгоно, устгагдсан нь дугааргүй болно
        Computer::resequence();
    }

    public function down(): void
    {
        // Хуучин (завсартай) дугаарыг сэргээх боломжгүй
    }
};
