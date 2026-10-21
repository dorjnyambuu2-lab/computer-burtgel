<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Компьютер бүрийн дэс дугаар (#1, #2, #3 ...). Устгагдсан ч дугаар нь дахин олгогдохгүй.
        Schema::table('computers', function (Blueprint $table) {
            $table->unsignedInteger('sequence_number')->nullable()->unique()->after('id');
        });

        // Одоо байгаа (устгагдсан ч) компьютеруудад бүртгэгдсэн дарааллаар нь дугаар олгоно
        $n = 0;
        DB::table('computers')->orderBy('id')->pluck('id')->each(function ($id) use (&$n) {
            DB::table('computers')->where('id', $id)->update(['sequence_number' => ++$n]);
        });

        // Сүүлд олгосон дугаарын тоолуур: бүрмөсөн устгасан ч дугаар буцаж олгогдохгүй
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });
        DB::table('sequences')->insert(['name' => 'computers', 'value' => $n]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');

        Schema::table('computers', function (Blueprint $table) {
            $table->dropUnique(['sequence_number']);
            $table->dropColumn('sequence_number');
        });
    }
};
