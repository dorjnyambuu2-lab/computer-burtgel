<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Хэрэглэгчийн код: эрхийн бүлгээр дараалсан — Superadmin, Админ: A1, A2, A3…; Ажилтан: U1, U2, U3…
     * (өгөгдлийн сангийн id дунд нь цоорхойтой байдаг тул). Бүртгэлийн дугаарын -A…-U… хэсэг энэ кодыг ашиглана.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
        });

        // Бүртгэгдсэн дарааллаар (id) дугаарлана
        $map = []; // хуучин id → шинэ дугаар (бүлгээр)
        $counters = ['A' => 0, 'U' => 0];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'role']) as $user) {
            $prefix = $user->role === 'employee' ? 'U' : 'A';
            $number = ++$counters[$prefix];
            DB::table('users')->where('id', $user->id)->update(['code' => $prefix.$number]);
            $map[$prefix][$user->id] = $number;
        }

        // Бүртгэлийн дугаарын A{id}, U{id} хэсгийг шинэ кодоор: PC-B1-A2-U82-20261022-001-5 → PC-B1-A2-U5-20261022-001-5
        // (QR код нь тусдаа qr_code тул нөлөөлөхгүй). Давхцахаас сэргийлж эхлээд түр утга өгнө.
        $rows = DB::table('computers')->whereNotNull('inventory_number')->get(['id', 'inventory_number']);
        $new = [];
        foreach ($rows as $row) {
            if (preg_match('/^(.+-B\d+)-A(\d+)-U(\d+)-(.+)$/', $row->inventory_number, $m)) {
                $admin = (int) $m[2] ? ($map['A'][(int) $m[2]] ?? $map['U'][(int) $m[2]] ?? 0) : 0;
                $holder = (int) $m[3] ? ($map['U'][(int) $m[3]] ?? $map['A'][(int) $m[3]] ?? 0) : 0;
                $value = "{$m[1]}-A{$admin}-U{$holder}-{$m[4]}";
                if ($value !== $row->inventory_number) {
                    $new[$row->id] = $value;
                }
            }
        }
        foreach ($new as $id => $value) {
            DB::table('computers')->where('id', $id)->update(['inventory_number' => '__tmp__'.$id]);
        }
        foreach ($new as $id => $value) {
            DB::table('computers')->where('id', $id)->update(['inventory_number' => $value]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
