<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Компьютерээс бусад төхөөрөмжийн үзүүлэлт (дэлгэцийн хэмжээ, printer-ийн төрөл, UPS-ийн чадал ...)
        Schema::table('computers', function (Blueprint $table) {
            $table->string('specs', 500)->nullable()->after('mac_address');
        });

        // Өмнө нь тэмдэглэлийн эхэнд «Үзүүлэлт: ...» гэж хадгалж байсныг шинэ багана руу шилжүүлнэ
        DB::table('computers')->where('notes', 'like', 'Үзүүлэлт: %')->orderBy('id')->get(['id', 'notes'])
            ->each(function ($row) {
                [$first, $rest] = array_pad(explode("\n", $row->notes, 2), 2, '');
                DB::table('computers')->where('id', $row->id)->update([
                    'specs' => trim(mb_substr($first, mb_strlen('Үзүүлэлт: '))),
                    'notes' => trim($rest) !== '' ? trim($rest) : null,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('computers')->whereNotNull('specs')->orderBy('id')->get(['id', 'specs', 'notes'])
            ->each(fn ($row) => DB::table('computers')->where('id', $row->id)->update([
                'notes' => trim('Үзүүлэлт: '.$row->specs."\n".($row->notes ?? '')),
            ]));

        Schema::table('computers', function (Blueprint $table) {
            $table->dropColumn('specs');
        });
    }
};
