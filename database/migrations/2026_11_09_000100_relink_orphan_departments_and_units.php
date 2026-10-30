<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Алба, нэгжийн тексттэй боловч жагсаалттай холбогдоогүй (department_id, unit_id хоосон) төхөөрөмж, хэрэглэгчийг
     * салбарын жагсаалттай дахин холбоно (байхгүй бол үүсгэнэ). «Алба хэлтэс» хуудасны ажилтны тоо 0 гарч байсныг засна.
     */
    public function up(): void
    {
        foreach (['department' => 'departments', 'unit' => 'units'] as $column => $listTable) {
            foreach (['computers', 'users'] as $table) {
                DB::table($table)->whereNotNull($column)->whereNull($column.'_id')->orderBy('id')
                    ->get(['id', 'branch_id', $column])
                    ->each(function ($row) use ($table, $column, $listTable) {
                        $name = trim(preg_replace('/\s+/u', ' ', (string) $row->$column));
                        if ($name === '') {
                            DB::table($table)->where('id', $row->id)->update([$column => null]);

                            return;
                        }
                        // Салбарын жагсаалтаас (том/жижиг үсэг ялгахгүй) хайж, байхгүй бол үүсгэнэ
                        $match = DB::table($listTable)->where('branch_id', $row->branch_id)->get(['id', 'name'])
                            ->first(fn ($d) => mb_strtolower($d->name) === mb_strtolower($name));
                        $id = $match?->id ?? DB::table($listTable)->insertGetId([
                            'branch_id' => $row->branch_id, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        DB::table($table)->where('id', $row->id)->update([$column.'_id' => $id, $column => $match?->name ?? $name]);
                    });
            }
        }
    }

    public function down(): void
    {
        // Өгөгдөл засах шилжилт: буцаах шаардлагагүй
    }
};
