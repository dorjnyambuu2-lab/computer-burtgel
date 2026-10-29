<?php

use App\Enums\ComputerStatus;
use App\Models\Computer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Иж бүрдлийн хэрэгслүүд үндсэн компьютерийнхээ төлөв, хариуцагчийг дагадаг болохоос (Computer::booted) өмнө
     * зөрсөн хэрэгслүүдийг нэг удаа тааруулна (жишээ нь компьютер «Ашиглагдаж байгаа», дэлгэц нь «Шинэ»).
     * Засварт, актласан хэрэгсэл, «Засварт» байгаа үндсэн компьютерийн хэрэгслүүд хөдлөхгүй.
     * Model-оор хадгална: өөрчлөлт бүр үйлдлийн түүхэнд бичигдэнэ.
     */
    public function up(): void
    {
        $skip = [ComputerStatus::InRepair, ComputerStatus::Decommissioned];

        Computer::$transferring = true; // иж бүрдлийн хэсэг: хариуцагч солих хоригийг алгасна
        try {
            Computer::withoutGlobalScopes()->whereNull('deleted_at')->whereNotNull('kit_parent_id')
                ->whereNotIn('status', $skip)
                ->with(['kitParent' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')])
                ->get()
                ->each(function (Computer $item) use ($skip) {
                    $main = $item->kitParent;
                    if (! $main || in_array($main->status, $skip, true)) {
                        return;
                    }
                    $item->status = $main->status;
                    $item->assigned_user_id = $main->assigned_user_id;
                    if (! $main->assigned_user_id) {
                        $item->assigned_to = null;
                    }
                    if ($item->isDirty()) {
                        $item->save();
                    }
                });
        } finally {
            Computer::$transferring = false;
        }
    }

    public function down(): void
    {
        // Өмнөх (зөрсөн) төлөвийг сэргээх шаардлагагүй
    }
};
