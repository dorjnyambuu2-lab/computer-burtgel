<?php

use App\Support\SpecSuggestions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Бүртгэлтэй дискийг «хэмжээ + төрөл» хэлбэрт: «SSD 512GB» → «512GB SSD», «SSD 256GB + HDD 1TB» → «256GB SSD + 1TB HDD»
     */
    public function up(): void
    {
        DB::table('computers')->whereNotNull('storage')->where('storage', '!=', '')->orderBy('id')
            ->select(['id', 'storage'])->each(function ($row) {
                $value = SpecSuggestions::storage($row->storage);
                if ($value !== $row->storage) {
                    DB::table('computers')->where('id', $row->id)->update(['storage' => $value]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
