<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Нэгжгүй албыг бүртгэлээс нь нэгжид харьяалуулна: тухайн албын төхөөрөмж, ажилтанд хамгийн олон бичигдсэн
     * (хоосон биш, ижил салбарын) нэгж. Тэнцүү бол аль нь болохыг мэдэхгүй тул нэгжгүй үлдээнэ.
     */
    public function up(): void
    {
        DB::table('departments')->whereNull('unit_id')->get(['id', 'branch_id'])->each(function ($dept) {
            $counts = DB::table('computers')->whereNull('deleted_at')->where('department_id', $dept->id)->whereNotNull('unit_id')->pluck('unit_id')
                ->merge(DB::table('users')->where('department_id', $dept->id)->whereNotNull('unit_id')->pluck('unit_id'))
                ->countBy()->sortDesc();
            if ($counts->isEmpty() || ($counts->count() > 1 && $counts->values()[0] === $counts->values()[1])) {
                return;
            }
            $unitId = $counts->keys()->first();
            if (DB::table('units')->where('id', $unitId)->where('branch_id', $dept->branch_id)->exists()) {
                DB::table('departments')->where('id', $dept->id)->update(['unit_id' => $unitId, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Өгөгдөл засах шилжилт: буцаах шаардлагагүй
    }
};
