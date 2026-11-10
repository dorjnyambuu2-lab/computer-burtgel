<?php

use App\Enums\ComputerType;
use App\Enums\EquipmentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Үйлдвэрлэгч, загваргүй төхөөрөмжид систем өмнө нь нэрийг «Ангилал + бүртгэлийн дугаар»
 * («Mouse SA1-20261029-001-2») болгодог байсан. Дугаар жагсаалт бүрт тусдаа харагддаг тул нэрнээс хасна.
 * Зөвхөн яг энэ автомат хэлбэртэй (өөрийн дугаараар төгссөн) нэрийг өөрчилнө — хэрэглэгчийн бичсэн нэрэнд хүрэхгүй.
 * Үйлдлийн түүхэд бичихгүйн тулд шууд DB-ээр шинэчилнэ.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('computers')->whereNotNull('inventory_number')->orderBy('id')
            ->get(['id', 'name', 'inventory_number', 'category', 'type'])
            ->each(function ($c) {
                $label = EquipmentCategory::tryFrom((string) $c->category)?->label()
                    ?? ComputerType::tryFrom((string) $c->type)?->label() ?? 'Төхөөрөмж';
                if ($c->name === $label.' '.$c->inventory_number) {
                    DB::table('computers')->where('id', $c->id)->update(['name' => $label]);
                }
            });
    }

    public function down(): void
    {
        // Буцаах шаардлагагүй: нэр нь зөвхөн харагдах утга
    }
};
