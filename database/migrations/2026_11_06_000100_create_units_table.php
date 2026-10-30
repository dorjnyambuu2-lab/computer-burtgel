<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Нэгж тусдаа хүснэгтэд (алба хэлтэстэй адил): салбар бүрт нэр нь давтагдахгүй. Төхөөрөмж, хэрэглэгч unit_id-аар
     * холбогдоно (computers.unit, users.unit текст нь харуулах, хайхад хэвээр — Unit нэр солигдоход синк).
     * Одоо байгаа текстийг шилжүүлнэ: салбар дотор том/жижиг үсгээр л ялгаатай нэрүүд нэг нэгж болно.
     */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['branch_id', 'name']);
        });

        foreach (['computers', 'users'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('unit_id')->nullable()->after('unit')->constrained()->nullOnDelete();
            });
        }

        // [салбар|жижиг үсгээр нэр => unit id]
        $ids = [];
        foreach (['computers', 'users'] as $table) {
            DB::table($table)->whereNotNull('unit')->orderBy('id')->get(['id', 'branch_id', 'unit'])
                ->each(function ($row) use ($table, &$ids) {
                    $name = trim(preg_replace('/\s+/u', ' ', (string) $row->unit));
                    if ($name === '') {
                        DB::table($table)->where('id', $row->id)->update(['unit' => null]);

                        return;
                    }
                    $key = ($row->branch_id ?? '').'|'.mb_strtolower($name);
                    $ids[$key] ??= DB::table('units')->insertGetId([
                        'branch_id' => $row->branch_id, 'name' => $name, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $canonical = DB::table('units')->where('id', $ids[$key])->value('name');
                    DB::table($table)->where('id', $row->id)->update(['unit_id' => $ids[$key], 'unit' => $canonical]);
                });
        }
    }

    public function down(): void
    {
        foreach (['computers', 'users'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('unit_id'));
        }
        Schema::dropIfExists('units');
    }
};
