<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Бүртгэлийн дугаарын дараалал: бүртгэсэн админ, салбар, хариуцагч, төрөл, огноо, дэд дугаар.
     * PC-B1-A2-U3-20261022-001-5 → A2-B1-U3-PC-20261022-001-5 (хуучин PC-20261020-001 хэлбэр хэвээр).
     * QR код нь тусдаа qr_code тул нөлөөлөхгүй. Давхцахаас сэргийлж эхлээд түр утга өгнө.
     */
    public function up(): void
    {
        $this->rewrite('/^([A-Z]+)-(B\d+)-(S?A\d+)-(U\d+)-(\d{8}-.+)$/', '$3-$2-$4-$1-$5');
    }

    public function down(): void
    {
        $this->rewrite('/^(S?A\d+)-(B\d+)-(U\d+)-([A-Z]+)-(\d{8}-.+)$/', '$4-$2-$1-$3-$5');
    }

    private function rewrite(string $pattern, string $replacement): void
    {
        $new = [];
        foreach (DB::table('computers')->whereNotNull('inventory_number')->get(['id', 'inventory_number']) as $row) {
            if (preg_match($pattern, $row->inventory_number)) {
                $new[$row->id] = preg_replace($pattern, $replacement, $row->inventory_number);
            }
        }
        foreach ($new as $id => $value) {
            DB::table('computers')->where('id', $id)->update(['inventory_number' => '__tmp__'.$id]);
        }
        foreach ($new as $id => $value) {
            DB::table('computers')->where('id', $id)->update(['inventory_number' => $value]);
        }
    }
};
