<?php

use App\Models\Computer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дэс дугаар салбар бүрт тусдаа: Төв салбар #1–#14, IT салбар #1–#4 гэх мэт.
     * (Өмнө бүх салбарт нэг дугаарлалт байсан тул Админы жагсаалтад #13-ын дараа #18 гэж «алдагдсан» мэт харагддаг байв.)
     */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropUnique(['sequence_number']);
            $table->unique(['branch_id', 'sequence_number']);
        });

        Computer::resequence();
    }

    public function down(): void
    {
        // Бүх салбарт нэг дугаарлалт руу буцаана
        Schema::table('computers', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'sequence_number']);
        });
        $n = 0;
        foreach (DB::table('computers')->whereNull('deleted_at')->orderBy('created_at')->orderBy('id')->pluck('id') as $id) {
            DB::table('computers')->where('id', $id)->update(['sequence_number' => ++$n]);
        }
        Schema::table('computers', function (Blueprint $table) {
            $table->unique('sequence_number');
        });
    }
};
