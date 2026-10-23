<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Superadmin-ы код: A1 → SA1 (Superadmin-ууд SA1, SA2 … дараалан). Тэдний бүртгэсэн төхөөрөмжийн
     * бүртгэлийн дугаарын A хэсгийг ч солино: PC-B1-A1-U3-20261022-001 → PC-B1-SA1-U3-20261022-001
     */
    public function up(): void
    {
        $number = 0;
        foreach (DB::table('users')->where('role', 'superadmin')->orderBy('id')->get(['id', 'code']) as $user) {
            $code = 'SA'.(++$number);
            DB::table('users')->where('id', $user->id)->update(['code' => $code]);

            if ($user->code && $user->code !== $code) {
                DB::table('computers')->where('created_by', $user->id)
                    ->where('inventory_number', 'like', '%-'.$user->code.'-U%')
                    ->get(['id', 'inventory_number'])
                    ->each(fn ($row) => DB::table('computers')->where('id', $row->id)->update([
                        'inventory_number' => preg_replace('/^(.+-B\d+)-'.preg_quote($user->code, '/').'-U/', '$1-'.$code.'-U', $row->inventory_number),
                    ]));
            }
        }
    }

    public function down(): void
    {
        //
    }
};
