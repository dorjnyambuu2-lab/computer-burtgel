<?php

use App\Enums\ComputerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('type')->index();
        });

        // Одоо байгаа компьютерүүдийг "Төрөл"-өөр нь ангилна. Таарахгүй бол "Ангилаагүй" хэвээр үлдэнэ.
        $map = [
            'Суурин компьютер' => 'processor',
            'Зөөврийн компьютер' => 'laptop',
            'All-in-One' => 'processor',
            'Сервер' => 'processor',
            'Бусад' => 'other',
        ];

        foreach (ComputerType::cases() as $case) {
            $category = $map[$case->label()] ?? null;

            if ($category !== null) {
                DB::table('computers')->where('type', $case->value)->update(['category' => $category]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};