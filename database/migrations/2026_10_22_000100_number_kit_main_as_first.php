<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Иж бүрдлийн дэд дугаар: үндсэн компьютер -1, дагалдах хэрэгслүүд -2, -3 ...
     * (өмнө нь компьютер дэд дугааргүй, дагалдах хэрэгслүүд -1-ээс эхэлдэг байсан тул 4 төхөөрөмж 3 гэж харагддаг байв).
     * Жишээ: PC-0012, PC-0012-1, PC-0012-2 → PC-0012-1, PC-0012-2, PC-0012-3.
     */
    public function up(): void
    {
        $mains = DB::table('computers')->whereNull('kit_parent_id')->whereNull('kit_index')
            ->whereIn('id', DB::table('computers')->whereNotNull('kit_parent_id')->select('kit_parent_id'))
            ->get(['id', 'inventory_number']);

        foreach ($mains as $main) {
            $base = $main->inventory_number;
            // Дугаар давхцахгүйн тулд их дэд дугаараас нь эхэлж нэгээр ахиулна
            $items = DB::table('computers')->where('kit_parent_id', $main->id)->orderByDesc('kit_index')->get(['id', 'kit_index', 'inventory_number']);
            foreach ($items as $item) {
                $index = (int) $item->kit_index + 1;
                $update = ['kit_index' => $index];
                if ($item->inventory_number === $base.'-'.$item->kit_index) {
                    $update['inventory_number'] = $base.'-'.$index;
                }
                DB::table('computers')->where('id', $item->id)->update($update);
            }

            $update = ['kit_index' => 1];
            if (! DB::table('computers')->where('inventory_number', $base.'-1')->exists()) {
                $update['inventory_number'] = $base.'-1';
            }
            DB::table('computers')->where('id', $main->id)->update($update);
        }
    }

    public function down(): void
    {
        $mains = DB::table('computers')->whereNull('kit_parent_id')->where('kit_index', 1)->get(['id', 'inventory_number']);

        foreach ($mains as $main) {
            $base = str_ends_with($main->inventory_number, '-1') ? substr($main->inventory_number, 0, -2) : $main->inventory_number;
            DB::table('computers')->where('id', $main->id)->update(['kit_index' => null, 'inventory_number' => $base]);

            $items = DB::table('computers')->where('kit_parent_id', $main->id)->orderBy('kit_index')->get(['id', 'kit_index', 'inventory_number']);
            foreach ($items as $item) {
                $index = (int) $item->kit_index - 1;
                $update = ['kit_index' => $index];
                if ($item->inventory_number === $base.'-'.$item->kit_index) {
                    $update['inventory_number'] = $base.'-'.$index;
                }
                DB::table('computers')->where('id', $item->id)->update($update);
            }
        }
    }
};
