<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IP, MAC хаяг төхөөрөмж бүрт өөр байхыг өгөгдлийн сангийн түвшинд баталгаажуулна.
     * Хоосон (NULL) утга олон байж болно.
     */
    public function up(): void
    {
        // MAC-г нэг хэлбэрт: aa-bb-.. → AA:BB:..
        DB::table('computers')->whereNotNull('mac_address')
            ->update(['mac_address' => DB::raw("upper(replace(trim(mac_address), '-', ':'))")]);
        DB::table('computers')->where('ip_address', '')->update(['ip_address' => null]);
        DB::table('computers')->where('mac_address', '')->update(['mac_address' => null]);

        // Устгасан (хогийн саванд) төхөөрөмжийн хаягийг чөлөөлнө
        DB::table('computers')->whereNotNull('deleted_at')->update(['ip_address' => null, 'mac_address' => null]);

        // Давхардсан бол сүүлд шинэчлэгдсэнд нь үлдээж бусдаас хасна
        foreach (['ip_address', 'mac_address'] as $column) {
            $duplicates = DB::table('computers')->whereNotNull($column)
                ->select($column)->groupBy($column)->havingRaw('count(*) > 1')->pluck($column);
            foreach ($duplicates as $value) {
                $keep = DB::table('computers')->where($column, $value)
                    ->orderByDesc('network_updated_at')->orderByDesc('updated_at')->orderByDesc('id')->value('id');
                DB::table('computers')->where($column, $value)->where('id', '!=', $keep)->update([$column => null]);
            }
        }

        Schema::table('computers', function (Blueprint $table) {
            $table->unique('ip_address');
            $table->unique('mac_address');
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropUnique(['ip_address']);
            $table->dropUnique(['mac_address']);
        });
    }
};
